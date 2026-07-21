<?php

namespace App\Ai\Agents;

use App\Support\SqlGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Turns a natural-language question about the IMBY planning warehouse into a
 * single read-only PostgreSQL SELECT. The generated SQL is validated by
 * {@see SqlGuard} and executed against the read-only connection.
 *
 * Temperature is pinned to 0 so SQL generation is as deterministic as possible.
 */
#[Temperature(0)]
class InsightsAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        You are IMBY's planning-data analyst. Convert the user's question into ONE
        read-only PostgreSQL SELECT query against ONLY the tables and columns below.

        Tables (schema "public"):
        - authorities(id, name, region, state, country, amalgamated, statistics_code,
          postal_suburb, postal_code, phone, email, url, tracking [boolean],
          tracking_system, lga_name, council_name)
        - applications(id, authority_id, authority_no, portal_no, type, description,
          estimated_cost [numeric], submitted [date], decision, decision_date [date],
          created_at)
        - legislation(id, name)
        - application_classes(id, name)  development_classes(id, name)  decision_classes(id, name)
        - application_types(id, name, application_class_id)
        - development_types(id, name, development_class_id)
        - decision_types(id, name, decision_class_id)

        Rules:
        - Output a SELECT (or WITH ... SELECT) query ONLY. Never write, update, or alter data.
        - Use ONLY the tables/columns listed above. Do not reference system catalogs.
        - Answer questions about councils/authorities using ONLY the `authorities` table,
          which already has `state`, `region`, and `tracking` columns. Do NOT join to
          `applications` unless the question is specifically about development applications.
        - For "how many ... per X" questions use COUNT(*) with GROUP BY X (do not use DISTINCT joins).
        - Always include a LIMIT (at most 200).
        - Put the query in "sql" and a one-sentence description in "explanation".
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sql' => $schema->string()->required(),
            'explanation' => $schema->string()->required(),
        ];
    }
}
