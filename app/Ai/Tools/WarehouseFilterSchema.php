<?php

namespace App\Ai\Tools;

use App\Support\Insights\FilterFromTool;
use App\Support\Insights\ToolJson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;

/**
 * Shared optional warehouse filter fields used by search/stats/forecast tools.
 */
trait WarehouseFilterSchema
{
    /**
     * @return array<string, Type>
     */
    protected function filterSchema(JsonSchema $schema): array
    {
        return [
            'state' => $schema->string()->description('Australian state/territory code, e.g. NSW, ACT, VIC.'),
            'suburb' => $schema->string()->description('Development site suburb (not council postal suburb).'),
            'authority_id' => $schema->integer()->description('Council / LGA authority id.'),
            'location_id' => $schema->integer()->description('Specific location id.'),
            'search' => $schema->string()->description('Free-text search across descriptions / names.'),
            'submitted_from' => $schema->string()->description('ISO date lower bound for applications.submitted.'),
            'submitted_to' => $schema->string()->description('ISO date upper bound for applications.submitted.'),
            'estimated_cost_min' => $schema->number(),
            'estimated_cost_max' => $schema->number(),
            'application_class_ids' => $schema->string()->description('Comma-separated application class ids from ListTaxonomies.'),
            'development_class_ids' => $schema->string()->description('Comma-separated development class ids (BCA/NCC classes).'),
            'decision_class_ids' => $schema->string()->description('Comma-separated decision class ids (Approved, In Progress, …).'),
            'application_type_ids' => $schema->string()->description('Comma-separated application type ids from ListTaxonomies.'),
            'development_type_ids' => $schema->string()->description('Comma-separated development type ids from ListTaxonomies.'),
            'decision_type_ids' => $schema->string()->description('Comma-separated decision type ids from ListTaxonomies.'),
            'legislation_ids' => $schema->string()->description('Comma-separated legislation ids.'),
            'include_amalgamated' => $schema->boolean()->description('Include former/amalgamated councils. Default false.'),
        ];
    }

    protected function encodeFiltered(Request $request, callable $build): string
    {
        try {
            return ToolJson::encode($build(FilterFromTool::make($request)));
        } catch (\Throwable $e) {
            return ToolJson::encode(['error' => $e->getMessage()]);
        }
    }
}
