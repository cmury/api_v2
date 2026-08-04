<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ChartRequest;
use App\Http\Requests\Warehouse\StatsRequest;
use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\StatsQuery;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class StatsController extends Controller
{
    public function __construct(
        private readonly StatsQuery $statsQuery = new StatsQuery,
    ) {}

    /**
     * Aggregate a warehouse metric for the given filters.
     *
     * Required: `metric` (`applications`, `estimated_costs`, `decision_duration`,
     * `application_types`, `development_types`, `decision_classes`).
     *
     * Scope the count with filters such as `state`, `authority_id`, `location_id`,
     * class/legislation ids, date range, or map bounds — not a `scope` query param.
     * The response `data.scope` is derived from those filters (`all`, `state`,
     * `authority`, `location`, or `map`).
     *
     * Example: `GET /stats?metric=applications&state=NSW`
     */
    public function show(StatsRequest $request): JsonResponse
    {
        $filter = ApplicationFilter::fromArray($request->validated());

        try {
            $result = $this->statsQuery->metric((string) $request->input('metric'), $filter);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'stats',
            'data' => $result,
        ]);
    }

    /**
     * Chart a warehouse metric. Response `data` always includes Chart.js-friendly
     * `labels` + `values` (plus `series` for calendar and timeseries).
     *
     * Required: `metric`. Optional: `format` (`auto`, `timeseries`, `calendar`,
     * `categorical`, `bands`), `interval` (timeseries), `limit` (categorical),
     * and the same filter params as `/stats` (`state`, `authority_id`, …).
     *
     * Examples:
     * - `?metric=applications&format=timeseries&interval=month`
     * - `?metric=applications&format=calendar&authority_id=12`
     * - `?metric=application_types&format=categorical&limit=9&state=NSW`
     * - `?metric=estimated_costs&format=bands`
     * - `?metric=estimated_costs&format=timeseries&interval=month`
     *
     * Shapes by format:
     * - categorical / bands: `labels[]`, `values[]`
     * - calendar: `labels[]` months, `series[]` years, `values[][]` matrix
     * - timeseries: `labels[]` periods, `values[]` primary metric, `series[]` full points
     */
    public function chart(ChartRequest $request): JsonResponse
    {
        $filter = ApplicationFilter::fromArray($request->validated());
        $interval = (string) $request->input('interval', 'month');
        $format = (string) $request->input('format', 'auto');
        $limit = (int) $request->input('limit', 9);

        try {
            $result = $this->statsQuery->chart(
                (string) $request->input('metric'),
                $filter,
                $interval,
                $format,
                $limit,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'chart',
            'data' => $result,
        ]);
    }
}
