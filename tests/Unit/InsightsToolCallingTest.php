<?php

namespace Tests\Unit;

use App\Ai\Agents\InsightsAgent;
use App\Ai\Tools\GetApplication;
use App\Ai\Tools\GetAuthority;
use App\Ai\Tools\GetForecast;
use App\Ai\Tools\GetStats;
use App\Ai\Tools\ListTaxonomies;
use App\Ai\Tools\LookupApiDocs;
use App\Ai\Tools\RunWarehouseSql;
use App\Ai\Tools\SearchApplications;
use App\Ai\Tools\SearchApplicationsNearFacility;
use App\Ai\Tools\SearchAuthorities;
use App\Ai\Tools\SearchFacilities;
use App\Ai\Tools\SearchLocations;
use App\Support\Insights\InsightsPromptContext;
use App\Support\Insights\OpenApiCatalog;
use App\Support\Insights\ToolJson;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use Tests\TestCase;

class InsightsToolCallingTest extends TestCase
{
    public function test_agent_exposes_warehouse_tools(): void
    {
        $agent = new InsightsAgent;
        $this->assertInstanceOf(HasTools::class, $agent);

        $names = [];
        foreach ($agent->tools() as $tool) {
            $this->assertInstanceOf(Tool::class, $tool);
            $names[] = ToolNameResolver::resolve($tool);
        }

        $this->assertSame([
            'lookup_api_docs',
            'search_authorities',
            'get_authority',
            'search_applications',
            'get_application',
            'search_locations',
            'search_facilities',
            'search_applications_near_facility',
            'get_stats',
            'get_forecast',
            'list_taxonomies',
            'run_warehouse_sql',
        ], $names);
    }

    public function test_agent_structured_output_includes_answer(): void
    {
        $schema = (new InsightsAgent)->schema(new JsonSchemaTypeFactory);

        $this->assertArrayHasKey('answer', $schema);
        $this->assertArrayHasKey('explanation', $schema);
        $this->assertArrayHasKey('confidence', $schema);
    }

    public function test_openapi_catalog_exposes_intent_guide(): void
    {
        $guide = OpenApiCatalog::intentGuide();

        $this->assertNotSame('', $guide);
        $this->assertStringContainsString('/stats', $guide);
    }

    public function test_openapi_catalog_search_finds_authority_paths(): void
    {
        $hits = OpenApiCatalog::search('authority phone council');

        $this->assertNotEmpty($hits);
        $paths = array_column($hits, 'path');
        $this->assertTrue(
            collect($paths)->contains(fn (string $path) => str_contains($path, 'authorit')),
            'Expected an authorities-related OpenAPI path',
        );
    }

    public function test_lookup_api_docs_tool_returns_matches(): void
    {
        $json = (new LookupApiDocs)->handle(new Request([
            'query' => 'forecast applications',
            'limit' => 5,
        ]));

        $decoded = json_decode((string) $json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('matches', $decoded);
        $this->assertNotEmpty($decoded['matches']);
    }

    public function test_tool_json_truncates_large_payloads(): void
    {
        $encoded = ToolJson::encode(['blob' => str_repeat('x', 20_000)], 1_000);
        $decoded = json_decode($encoded, true);

        $this->assertTrue($decoded['truncated'] ?? false);
    }

    public function test_prompt_context_marks_short_follow_ups(): void
    {
        $this->assertFalse(InsightsPromptContext::isStandaloneQuestion('add the application number'));
        $this->assertTrue(InsightsPromptContext::isStandaloneQuestion('How many authorities are there in NSW?'));
    }

    public function test_tool_classes_are_instantiable(): void
    {
        foreach ([
            SearchAuthorities::class,
            GetAuthority::class,
            SearchApplications::class,
            GetApplication::class,
            SearchLocations::class,
            SearchFacilities::class,
            SearchApplicationsNearFacility::class,
            GetStats::class,
            GetForecast::class,
            ListTaxonomies::class,
            LookupApiDocs::class,
            RunWarehouseSql::class,
        ] as $class) {
            $this->assertInstanceOf(Tool::class, new $class);
        }
    }
}
