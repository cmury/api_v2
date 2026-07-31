<?php

namespace App\Ai\Tools;

use App\Models\Application;
use App\Support\Insights\FilterFromTool;
use App\Support\Insights\ToolJson;
use App\Support\Warehouse\ApplicationsNearFacility;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/facilities/applications-near.
 */
class SearchApplicationsNearFacility implements Tool
{
    use ReadsToolArgs;
    use WarehouseFilterSchema;

    public function __construct(
        private readonly ApplicationsNearFacility $applicationsNearFacility = new ApplicationsNearFacility,
    ) {}

    public function name(): string
    {
        return 'search_applications_near_facility';
    }

    public function description(): Stringable|string
    {
        return 'Find development applications within a radius (metres) of a facility (station, school, …). '
            .'Resolve via facility_id (from search_facilities) or facility_search (name). '
            .'Filter with class/type ids from list_taxonomies and optional source (nsw-eplanning, act-dafinder). '
            .'Example: Construction Certificates near Chatswood Railway Station.';
    }

    public function handle(Request $request): Stringable|string
    {
        $perPage = max(1, min(50, $this->argInt($request, 'per_page', 10) ?? 10));
        $radius = max(1, min(50_000, $this->argInt($request, 'radius', 1000) ?? 1000));
        $filter = FilterFromTool::make($request);

        $filterInput = array_filter([
            'state' => $filter->state,
            'suburb' => $filter->suburb,
            'authority_id' => $filter->authorityId,
            'search' => $filter->search,
            'submitted_from' => $filter->submittedFrom?->toDateString(),
            'submitted_to' => $filter->submittedTo?->toDateString(),
            'source' => $filter->source,
            'estimated_cost_min' => $filter->estimatedCostMin,
            'estimated_cost_max' => $filter->estimatedCostMax,
            'application_class_ids' => $filter->applicationClassIds,
            'development_class_ids' => $filter->developmentClassIds,
            'decision_class_ids' => $filter->decisionClassIds,
            'application_type_ids' => $filter->applicationTypeIds,
            'development_type_ids' => $filter->developmentTypeIds,
            'decision_type_ids' => $filter->decisionTypeIds,
            'legislation_ids' => $filter->legislationIds,
            'amalgamated' => $filter->includeAmalgamated,
        ], static fn ($v) => $v !== null && $v !== [] && $v !== '');

        try {
            $resolved = $this->applicationsNearFacility->query(
                $filterInput,
                $radius,
                $this->argInt($request, 'facility_id'),
                $this->hasArg($request, 'facility_search') ? $this->argString($request, 'facility_search') : null,
                $this->hasArg($request, 'facility_type') ? $this->argString($request, 'facility_type') : null,
                $filter->state,
            );
        } catch (InvalidArgumentException $e) {
            return ToolJson::encode(['error' => $e->getMessage()]);
        }

        $order = $this->argString($request, 'order', '-submitted') ?: '-submitted';
        $direction = str_starts_with($order, '-') ? 'desc' : 'asc';
        $column = ltrim($order, '-');
        if (! in_array($column, ['submitted', 'estimated_cost', 'authority_no', 'portal_no'], true)) {
            $column = 'submitted';
        }

        /** @var Builder<Application> $query */
        $query = $resolved['query'];
        $rows = $query->orderBy($column, $direction)->limit($perPage)->get([
            'id', 'authority_id', 'authority_no', 'portal_no', 'type', 'description',
            'estimated_cost', 'submitted', 'decision', 'decision_date', 'source',
        ]);

        return ToolJson::encode([
            'facility' => $resolved['facility'],
            'radius_meters' => $resolved['radius_meters'],
            'count' => $rows->count(),
            'applications' => $rows->map(fn (Application $a) => [
                'id' => $a->id,
                'authority_id' => $a->authority_id,
                'authority' => $a->authority?->name,
                'authority_no' => $a->authority_no,
                'portal_no' => $a->portal_no,
                'type' => $a->type,
                'description' => $a->description,
                'estimated_cost' => $a->estimated_cost,
                'submitted' => $a->submitted,
                'decision' => $a->decision,
                'decision_date' => $a->decision_date,
                'source' => $a->source,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->filterSchema($schema) + [
            'facility_id' => $schema->integer()->description('Preferred: id from search_facilities.'),
            'facility_search' => $schema->string()->description('Facility name when id is unknown.'),
            'facility_type' => $schema->string()->description('Optional type hint: train | bus | primary_school | …'),
            'radius' => $schema->integer()->description('Distance in metres (default 1000, max 50000).'),
            'order' => $schema->string()->description('submitted, estimated_cost, authority_no; prefix - for desc.'),
            'per_page' => $schema->integer()->description('Max rows (1–50, default 10).'),
        ];
    }
}
