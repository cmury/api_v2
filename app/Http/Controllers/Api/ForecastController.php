<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ForecastRequest;
use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\ForecastQuery;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ForecastController extends Controller
{
    public function __construct(
        private readonly ForecastQuery $forecastQuery = new ForecastQuery,
    ) {}

    /**
     * Forecast application volume from historical submissions.
     *
     * Method: seasonal moving average with low/high bands. Chart.js-friendly
     * `labels` + `values` for a single series (`group_by=none`), or `groups[]`
     * when projecting several states / authorities / suburbs.
     *
     * Examples:
     * - State volume: `GET /forecasts?state=NSW&horizon=3`
     * - Suburb volume: `GET /forecasts?suburb=Manly&state=NSW&horizon=6`
     * - Top suburbs in an LGA: `GET /forecasts?group_by=suburb&authority_id=42&limit=5`
     * - Compare states: `GET /forecasts?group_by=state&limit=8`
     *
     * Optional: `history_months` (default 24), `horizon` / `horizon_months` (default 3),
     * shared filters (`application_class_ids`, `legislation_ids`, bounds, …).
     */
    public function show(ForecastRequest $request): JsonResponse
    {
        $metric = strtolower((string) $request->input('metric', 'applications'));
        if ($metric !== 'applications') {
            return response()->json([
                'message' => "Unknown forecast metric [{$metric}].",
            ], 422);
        }

        $filter = ApplicationFilter::fromArray($request->validated());
        $groupBy = (string) $request->input('group_by', 'none');
        $horizon = (int) ($request->input('horizon') ?: $request->input('horizon_months') ?: 3);
        $historyMonths = (int) ($request->input('history_months') ?: 24);
        $limit = (int) ($request->input('limit') ?: 10);

        try {
            $result = $this->forecastQuery->volume(
                $filter,
                groupBy: $groupBy,
                horizonMonths: $horizon,
                historyMonths: $historyMonths,
                limit: $limit,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'forecast',
            'data' => $result,
        ]);
    }
}
