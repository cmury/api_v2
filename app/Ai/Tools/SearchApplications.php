<?php

namespace App\Ai\Tools;

use App\Models\Application;
use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\ApplicationQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/applications — browse / filter DAs.
 */
class SearchApplications implements Tool
{
    use ReadsToolArgs;
    use WarehouseFilterSchema;

    public function __construct(
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
    ) {}

    public function name(): string
    {
        return 'search_applications';
    }

    public function description(): Stringable|string
    {
        return 'Search development applications with shared warehouse filters (state, suburb, authority_id, '
            .'class ids, date/cost). Use for lists and ranked values. Prefer get_stats for pure counts.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->encodeFiltered($request, function (ApplicationFilter $filter) use ($request): array {
            $perPage = max(1, min(50, $this->argInt($request, 'per_page', 10) ?? 10));
            $order = $this->argString($request, 'order', '-submitted') ?: '-submitted';
            $direction = str_starts_with($order, '-') ? 'desc' : 'asc';
            $column = ltrim($order, '-');
            if (! in_array($column, ['submitted', 'estimated_cost', 'authority_no', 'portal_no'], true)) {
                $column = 'submitted';
            }

            $query = Application::query()->with(['authority:id,name,state']);
            $this->applicationQuery->applyToApplications($query, $filter);
            $query->orderBy($column, $direction)->limit($perPage);

            $rows = $query->get([
                'id', 'authority_id', 'authority_no', 'portal_no', 'type', 'description',
                'estimated_cost', 'submitted', 'decision', 'decision_date',
            ]);

            return [
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
                ])->all(),
            ];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->filterSchema($schema) + [
            'order' => $schema->string()->description('submitted, estimated_cost, authority_no; prefix - for desc.'),
            'per_page' => $schema->integer()->description('Max rows (1–50, default 10).'),
        ];
    }
}
