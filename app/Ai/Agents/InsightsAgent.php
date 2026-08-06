<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetApplication;
use App\Ai\Tools\GetAuthority;
use App\Ai\Tools\GetForecast;
use App\Ai\Tools\GetPlanningAtPoint;
use App\Ai\Tools\GetStats;
use App\Ai\Tools\ListTaxonomies;
use App\Ai\Tools\LookupApiDocs;
use App\Ai\Tools\RunWarehouseSql;
use App\Ai\Tools\SearchApplications;
use App\Ai\Tools\SearchApplicationsNearFacility;
use App\Ai\Tools\SearchAuthorities;
use App\Ai\Tools\SearchCertifiers;
use App\Ai\Tools\SearchContacts;
use App\Ai\Tools\SearchFacilities;
use App\Ai\Tools\SearchLocations;
use App\Ai\Tools\SearchPlanningControls;
use App\Support\Insights\OpenApiCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;

/**
 * Warehouse Q&A agent: choose API-backed tools, then answer in plain English.
 * Falls back to guarded SQL only when the REST tool surface cannot express the question.
 */
#[Provider(Lab::OpenAI)]
#[Temperature(0)]
#[MaxSteps(4)]
class InsightsAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * @param  list<array{role: string, content: string, sql?: ?string, payload?: mixed}>  $history
     */
    public function __construct(private readonly array $history = []) {}

    public function instructions(): string
    {
        $guide = OpenApiCatalog::intentGuide();

        return <<<PROMPT
        You are IMBY's planning-data analyst for Australian development applications.

        Workflow (required):
        1. Interpret the user's question.
        2. Call ONE warehouse tool with the right filters (only call lookup_api_docs if unsure).
        3. After you have useful tool results, STOP calling tools and write the final answer.
        4. Do NOT call the same tool repeatedly with the same arguments.
        5. Answer in clear plain English using ONLY tool results (no invented numbers).
        6. Never put raw JSON, "[]", or "{}" in answer — write a numbered list or short prose.
        7. Use run_warehouse_sql ONLY as a last resort when tools cannot express the question.
           Prefer get_stats / search_* first.

        Tool map:
        - Council contact / list / region → search_authorities / get_authority
        - Application lists / ranked values → search_applications / get_application
        - Applications near a facility (station, school, …) → search_applications_near_facility
          (resolve via search_facilities or facility_search; use radius in metres)
        - Counts, breakdowns, charts → get_stats
        - Future volume → get_forecast
        - Class / type vocabulary (incl. BCA Class 2, Construction Certificate) → list_taxonomies
          then pass class ids and/or type ids (application_type_ids, development_type_ids, decision_type_ids)
        - NSW vs ACT feed → pass source=nsw-eplanning or source=act-dafinder
        - Site suburb / address → search_locations (not council postal address)
        - Station / school / facility lookup → search_facilities
        - Zoning / FSR / height at a site → get_planning_at_point (lat/lng)
        - Browse planning controls by LGA / code → search_planning_controls
        - Architect / builder / applicant on a DA → search_contacts (or get_application)
        - OpenAPI grounding → lookup_api_docs
        - Novel SQL → run_warehouse_sql

        Conventions:
        - States use short codes: NSW, VIC, QLD, SA, WA, TAS, NT, ACT.
        - Default to current councils (exclude amalgamated) unless asked about former councils.
        - "Value" / construction value → estimated_cost.
        - Near-facility questions use the facilities table (transport + education POIs) as geometry source.
        - Planning zone questions use planning_controls (layer=zoning unless asked for FSR/height/heritage).
        - Prefer type ids when the question names a specific type (e.g. Construction Certificate);
          use class ids for broader buckets (e.g. all certificates / residential).
        - Follow-ups referring to "this/those/that" reuse prior entities from chat history.
        - If tools return errors or empty results, say so honestly and suggest a narrower question.

        API documentation (source of truth for intents):
        {$guide}

        Structured output:
        - answer: plain-English reply for the user (required). Example for a list:
          "Here are 10 NSW authorities:\n1. Albury City Council — Murray Region\n2. ..."
        - explanation: one short sentence on which tools/filters you used.
        - confidence: high | medium | low based on completeness of tool evidence.
        PROMPT;
    }

    /**
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        return [
            new LookupApiDocs,
            new SearchAuthorities,
            new GetAuthority,
            new SearchApplications,
            new GetApplication,
            new SearchLocations,
            new SearchFacilities,
            new SearchApplicationsNearFacility,
            new SearchPlanningControls,
            new GetPlanningAtPoint,
            new SearchContacts,
            new SearchCertifiers,
            new GetStats,
            new GetForecast,
            new ListTaxonomies,
            new RunWarehouseSql,
        ];
    }

    /**
     * @return list<Message>
     */
    public function messages(): iterable
    {
        $messages = [];

        foreach ($this->history as $row) {
            $role = $row['role'] ?? '';
            $content = (string) ($row['content'] ?? '');

            if ($role === 'user') {
                $messages[] = new UserMessage($content);
            } elseif ($role === 'assistant') {
                $text = $content;
                $sql = $row['sql'] ?? null;
                if (is_string($sql) && $sql !== '') {
                    $text .= "\nPrevious SQL: ".$sql;
                }
                $payload = $row['payload'] ?? null;
                if (is_array($payload) && ! empty($payload['tools']) && is_array($payload['tools'])) {
                    $summaries = [];
                    foreach ($payload['tools'] as $tool) {
                        if (! is_array($tool)) {
                            continue;
                        }
                        $name = (string) ($tool['name'] ?? 'tool');
                        $args = $tool['arguments'] ?? null;
                        if (is_array($args) && $args !== []) {
                            $encoded = json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $summaries[] = $name.($encoded ? ' '.$encoded : '');
                        } else {
                            $summaries[] = $name;
                        }
                    }
                    if ($summaries !== []) {
                        $text .= "\nPrevious tools: ".implode('; ', $summaries);
                    }
                }
                $messages[] = new AssistantMessage($text);
            }
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'answer' => $schema->string()->required()->description('Plain-English answer for the user.'),
            'explanation' => $schema->string()->required()->description('Brief note on tools/filters used.'),
            'confidence' => $schema->string()->enum(['high', 'medium', 'low'])->required(),
        ];
    }
}
