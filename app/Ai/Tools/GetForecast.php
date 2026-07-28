<?php

namespace App\Ai\Tools;

use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\ForecastQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/forecasts — application volume projections.
 */
class GetForecast implements Tool
{
    use ReadsToolArgs;
    use WarehouseFilterSchema;

    public function __construct(
        private readonly ForecastQuery $forecastQuery = new ForecastQuery,
    ) {}

    public function name(): string
    {
        return 'get_forecast';
    }

    public function description(): Stringable|string
    {
        return 'Project future application volumes (seasonal moving average). Use for forecast / outlook / '
            .'next N months questions. Optional group_by: none, state, authority, suburb.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->encodeFiltered($request, function (ApplicationFilter $filter) use ($request): array {
            return $this->forecastQuery->volume(
                $filter,
                $this->argString($request, 'group_by', 'none') ?: 'none',
                max(1, min(24, $this->argInt($request, 'horizon', 3) ?? 3)),
                max(6, min(120, $this->argInt($request, 'history_months', 24) ?? 24)),
                max(1, min(50, $this->argInt($request, 'limit', 10) ?? 10)),
            );
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->filterSchema($schema) + [
            'group_by' => $schema->string()->description('none | state | authority | suburb'),
            'horizon' => $schema->integer()->description('Forecast months ahead (1–24, default 3).'),
            'history_months' => $schema->integer()->description('Lookback months (6–120, default 24).'),
            'limit' => $schema->integer()->description('Top-N groups when group_by is set.'),
        ];
    }
}
