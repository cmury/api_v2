<?php

namespace App\Support\Warehouse;

use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Application-volume forecasts from historical submitted counts.
 *
 * Method: seasonal moving average — for each future month, blend the average of
 * the same calendar month in the history window with a recent trailing average.
 * Low/high bands use ±1 historical standard deviation of same-month counts
 * (floored at 0).
 */
final class ForecastQuery
{
    public const METRICS = [
        'applications',
    ];

    public const GROUP_BY = [
        'none',
        'state',
        'authority',
        'suburb',
    ];

    public function __construct(
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
    ) {}

    /**
     * @return array{
     *     metric: string,
     *     method: string,
     *     interval: string,
     *     group_by: string,
     *     based_on: array{submitted_from: string, submitted_to: string, history_months: int},
     *     horizon: array{from: string, to: string, months: int},
     *     labels: list<string>,
     *     values: list<int>|null,
     *     series: list<array<string, mixed>>,
     *     groups: list<array<string, mixed>>|null
     * }
     */
    public function volume(
        ApplicationFilter $filter,
        string $groupBy = 'none',
        int $horizonMonths = 3,
        int $historyMonths = 24,
        int $limit = 10,
        ?Carbon $asOf = null,
    ): array {
        $groupBy = strtolower($groupBy);
        if (! in_array($groupBy, self::GROUP_BY, true)) {
            throw new InvalidArgumentException("Unknown group_by [{$groupBy}].");
        }

        $horizonMonths = max(1, min(24, $horizonMonths));
        $historyMonths = max(6, min(120, $historyMonths));
        $limit = max(1, min(50, $limit));

        $asOf ??= Carbon::now()->startOfMonth();
        $historyTo = $asOf->copy()->subMonth()->endOfMonth();
        $historyFrom = $asOf->copy()->subMonths($historyMonths)->startOfMonth();
        $horizonFrom = $asOf->copy()->startOfMonth();
        $horizonTo = $asOf->copy()->addMonths($horizonMonths - 1)->endOfMonth();

        $scoped = $this->withSubmittedWindow($filter, $historyFrom, $historyTo);

        $horizonLabels = [];
        for ($i = 0; $i < $horizonMonths; $i++) {
            $horizonLabels[] = $horizonFrom->copy()->addMonths($i)->format('Y-m');
        }

        if ($groupBy === 'none') {
            $history = $this->monthlyCounts($scoped);
            $projection = $this->projectSeries($history, $horizonLabels);

            return $this->envelope(
                groupBy: $groupBy,
                historyFrom: $historyFrom,
                historyTo: $historyTo,
                historyMonths: $historyMonths,
                horizonFrom: $horizonFrom,
                horizonTo: $horizonTo,
                horizonMonths: $horizonMonths,
                labels: $horizonLabels,
                values: array_map(fn (array $p) => $p['point'], $projection),
                series: $projection,
                groups: null,
            );
        }

        $entities = $this->topEntities($scoped, $groupBy, $limit);
        $groups = [];

        foreach ($entities as $entity) {
            $entityFilter = $this->filterForEntity($scoped, $groupBy, $entity);
            $history = $this->monthlyCounts($entityFilter);
            $projection = $this->projectSeries($history, $horizonLabels);

            $groups[] = [
                'key' => $entity['key'],
                'label' => $entity['label'],
                'history_count' => $entity['count'],
                'labels' => $horizonLabels,
                'values' => array_map(fn (array $p) => $p['point'], $projection),
                'series' => $projection,
            ];
        }

        return $this->envelope(
            groupBy: $groupBy,
            historyFrom: $historyFrom,
            historyTo: $historyTo,
            historyMonths: $historyMonths,
            horizonFrom: $horizonFrom,
            horizonTo: $horizonTo,
            horizonMonths: $horizonMonths,
            labels: $horizonLabels,
            values: null,
            series: [],
            groups: $groups,
        );
    }

    /**
     * Pure projection used by forecasts and unit tests.
     *
     * @param  array<string, int>  $history  keyed Y-m => count
     * @param  list<string>  $horizonLabels  Y-m periods to forecast
     * @return list<array{period: string, point: int, low: int, high: int}>
     */
    public function projectSeries(array $history, array $horizonLabels): array
    {
        if ($horizonLabels === []) {
            return [];
        }

        $byMonthOfYear = [];
        foreach ($history as $period => $count) {
            $month = (int) substr((string) $period, 5, 2);
            $byMonthOfYear[$month][] = (int) $count;
        }

        $recent = array_slice($history, -3, 3, true);
        $recentAvg = $recent === []
            ? 0.0
            : array_sum($recent) / count($recent);

        $out = [];
        foreach ($horizonLabels as $period) {
            $month = (int) substr($period, 5, 2);
            $seasonal = $byMonthOfYear[$month] ?? [];

            if ($seasonal !== []) {
                $seasonalAvg = array_sum($seasonal) / count($seasonal);
                $point = (0.7 * $seasonalAvg) + (0.3 * $recentAvg);
                $stdev = $this->stdev($seasonal);
            } else {
                $point = $recentAvg;
                $all = array_values($history);
                $stdev = $this->stdev($all);
            }

            $low = max(0, (int) round($point - $stdev));
            $high = max($low, (int) round($point + $stdev));
            $pointInt = max(0, (int) round($point));

            $out[] = [
                'period' => $period,
                'point' => $pointInt,
                'low' => $low,
                'high' => max($high, $pointInt),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function monthlyCounts(ApplicationFilter $filter): array
    {
        $query = Application::query()->from('applications as a')
            ->whereNotNull('a.submitted')
            ->selectRaw("to_char(date_trunc('month', a.submitted), 'YYYY-MM') AS period")
            ->selectRaw('COUNT(DISTINCT a.id) AS count')
            ->groupBy('period')
            ->orderBy('period');

        $this->applicationQuery->applyToApplications($query, $filter, 'a');

        $rows = $query->get();
        $series = [];
        foreach ($rows as $row) {
            $series[(string) $row->period] = (int) $row->count;
        }

        return $series;
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    private function topEntities(ApplicationFilter $filter, string $groupBy, int $limit): array
    {
        $connection = Application::query()->getConnection()->getName();

        $query = match ($groupBy) {
            'state' => DB::connection($connection)
                ->table('applications as a')
                ->join('authorities as auth', 'auth.id', '=', 'a.authority_id')
                ->whereNotNull('auth.state')
                ->selectRaw('auth.state AS key, auth.state AS label, COUNT(DISTINCT a.id) AS count')
                ->groupBy('auth.state')
                ->orderByDesc('count')
                ->limit($limit),
            'authority' => DB::connection($connection)
                ->table('applications as a')
                ->join('authorities as auth', 'auth.id', '=', 'a.authority_id')
                ->selectRaw('auth.id::text AS key, auth.name AS label, COUNT(DISTINCT a.id) AS count')
                ->groupBy('auth.id', 'auth.name')
                ->orderByDesc('count')
                ->limit($limit),
            'suburb' => DB::connection($connection)
                ->table('applications as a')
                ->join('application_locations as al', 'al.application_id', '=', 'a.id')
                ->join('locations as l', 'l.id', '=', 'al.location_id')
                ->whereNotNull('l.suburb')
                ->where('l.suburb', '!=', '')
                ->selectRaw('l.suburb AS key, l.suburb AS label, COUNT(DISTINCT a.id) AS count')
                ->groupBy('l.suburb')
                ->orderByDesc('count')
                ->limit($limit),
            default => throw new InvalidArgumentException("Unknown group_by [{$groupBy}]."),
        };

        $this->applicationQuery->applyToApplications($query, $filter, 'a');

        return $query->get()->map(fn ($row) => [
            'key' => (string) $row->key,
            'label' => (string) $row->label,
            'count' => (int) $row->count,
        ])->all();
    }

    /**
     * @param  array{key: string, label: string, count: int}  $entity
     */
    private function filterForEntity(ApplicationFilter $base, string $groupBy, array $entity): ApplicationFilter
    {
        return match ($groupBy) {
            'state' => $this->cloneFilter($base, state: $entity['key']),
            'authority' => $this->cloneFilter($base, authorityId: (int) $entity['key']),
            'suburb' => $this->cloneFilter($base, suburb: $entity['key']),
            default => $base,
        };
    }

    private function withSubmittedWindow(
        ApplicationFilter $filter,
        Carbon $from,
        Carbon $to,
    ): ApplicationFilter {
        return $this->cloneFilter($filter, submittedFrom: $from, submittedTo: $to);
    }

    private function cloneFilter(
        ApplicationFilter $filter,
        ?Carbon $submittedFrom = null,
        ?Carbon $submittedTo = null,
        ?string $state = null,
        ?int $authorityId = null,
        ?string $suburb = null,
    ): ApplicationFilter {
        return new ApplicationFilter(
            bounds: $filter->bounds,
            applicationClassIds: $filter->applicationClassIds,
            developmentClassIds: $filter->developmentClassIds,
            decisionClassIds: $filter->decisionClassIds,
            estimatedCostMin: $filter->estimatedCostMin,
            estimatedCostMax: $filter->estimatedCostMax,
            submittedFrom: $submittedFrom ?? $filter->submittedFrom,
            submittedTo: $submittedTo ?? $filter->submittedTo,
            state: $state ?? $filter->state,
            authorityId: $authorityId ?? $filter->authorityId,
            locationId: $filter->locationId,
            search: $filter->search,
            includeAmalgamated: $filter->includeAmalgamated,
            centerLat: $filter->centerLat,
            centerLng: $filter->centerLng,
            radiusMeters: $filter->radiusMeters,
            legislationIds: $filter->legislationIds,
            suburb: $suburb ?? $filter->suburb,
            applicationTypeIds: $filter->applicationTypeIds,
            developmentTypeIds: $filter->developmentTypeIds,
            decisionTypeIds: $filter->decisionTypeIds,
        );
    }

    /**
     * @param  list<int|float>  $values
     */
    private function stdev(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        $sumSq = 0.0;
        foreach ($values as $value) {
            $sumSq += ($value - $mean) ** 2;
        }

        return sqrt($sumSq / ($n - 1));
    }

    /**
     * @param  list<string>  $labels
     * @param  list<int>|null  $values
     * @param  list<array<string, mixed>>  $series
     * @param  list<array<string, mixed>>|null  $groups
     * @return array<string, mixed>
     */
    private function envelope(
        string $groupBy,
        Carbon $historyFrom,
        Carbon $historyTo,
        int $historyMonths,
        Carbon $horizonFrom,
        Carbon $horizonTo,
        int $horizonMonths,
        array $labels,
        ?array $values,
        array $series,
        ?array $groups,
    ): array {
        return [
            'metric' => 'applications',
            'method' => 'seasonal_moving_average',
            'interval' => 'month',
            'group_by' => $groupBy,
            'based_on' => [
                'submitted_from' => $historyFrom->toDateString(),
                'submitted_to' => $historyTo->toDateString(),
                'history_months' => $historyMonths,
            ],
            'horizon' => [
                'from' => $horizonFrom->toDateString(),
                'to' => $horizonTo->toDateString(),
                'months' => $horizonMonths,
            ],
            'labels' => $labels,
            'values' => $values,
            'series' => $series,
            'groups' => $groups,
        ];
    }
}
