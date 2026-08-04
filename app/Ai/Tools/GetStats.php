<?php

namespace App\Ai\Tools;

use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\StatsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/stats and optionally GET /api/charts.
 */
class GetStats implements Tool
{
    use ReadsToolArgs;
    use WarehouseFilterSchema;

    public function __construct(
        private readonly StatsQuery $statsQuery = new StatsQuery,
    ) {}

    public function name(): string
    {
        return 'get_stats';
    }

    public function description(): Stringable|string
    {
        return 'Aggregate warehouse metrics. Use for "how many", totals, breakdowns by application/development '
            .'type or decision class, average decision duration, and optional charts. Metrics: applications, '
            .'estimated_costs, decision_duration, application_types, development_types, decision_classes. '
            .'Prefer this for counts over SQL.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->encodeFiltered($request, function (ApplicationFilter $filter) use ($request): array {
            $metric = strtolower($this->argString($request, 'metric', 'applications') ?: 'applications');
            $mode = strtolower($this->argString($request, 'mode', 'metric') ?: 'metric');

            if ($mode === 'chart') {
                return $this->statsQuery->chart(
                    $metric,
                    $filter,
                    $this->argString($request, 'interval', 'month') ?: 'month',
                    $this->argString($request, 'format', 'auto') ?: 'auto',
                    max(1, min(25, $this->argInt($request, 'limit', 9) ?? 9)),
                );
            }

            return $this->statsQuery->metric($metric, $filter);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->filterSchema($schema) + [
            'metric' => $schema->string()->description('applications | estimated_costs | decision_duration | application_types | development_types | decision_classes'),
            'mode' => $schema->string()->description('metric (default) or chart'),
            'format' => $schema->string()->description('Chart only: auto | timeseries | calendar | categorical | bands'),
            'interval' => $schema->string()->description('Chart timeseries interval, e.g. month'),
            'limit' => $schema->integer()->description('Chart categorical limit (default 9)'),
        ];
    }
}
