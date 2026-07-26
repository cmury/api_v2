<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     * Collapsed count endpoint: ?metric=applications&scope=state&state=NSW
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
     * Collapsed chart endpoint.
     *
     * Examples:
     * - ?metric=applications&format=timeseries&interval=month
     * - ?metric=applications&format=calendar
     * - ?metric=application_types&format=categorical&limit=9
     * - ?metric=estimated_costs&format=bands
     * - ?metric=estimated_costs&format=timeseries&interval=month
     */
    public function chart(StatsRequest $request): JsonResponse
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
