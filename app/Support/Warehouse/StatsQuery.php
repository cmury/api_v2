<?php

namespace App\Support\Warehouse;

use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Collapsed replacement for the old per-scope count/chart endpoints.
 */
final class StatsQuery
{
    public const METRICS = [
        'applications',
        'estimated_costs',
        'decision_duration',
        'application_types',
        'development_types',
        'decision_classes',
    ];

    public const CHART_METRICS = [
        'applications',
        'estimated_costs',
        'application_types',
        'development_types',
        'decision_classes',
    ];

    /**
     * Fixed estimated-cost bands (AUD). Corrected from the old API's $1m band bug.
     *
     * @var list<array{label: string, min: float, max: float|null}>
     */
    public const COST_BANDS = [
        ['label' => '$0–$249k', 'min' => 0, 'max' => 250000],
        ['label' => '$250k–$499k', 'min' => 250000, 'max' => 500000],
        ['label' => '$500k–$749k', 'min' => 500000, 'max' => 750000],
        ['label' => '$750k–$999k', 'min' => 750000, 'max' => 1000000],
        ['label' => '$1.0m–$1.249m', 'min' => 1000000, 'max' => 1250000],
        ['label' => '$1.25m–$1.499m', 'min' => 1250000, 'max' => 1500000],
        ['label' => '$1.5m–$1.749m', 'min' => 1500000, 'max' => 1750000],
        ['label' => '$1.75m–$1.999m', 'min' => 1750000, 'max' => 2000000],
        ['label' => '$2.0m–$2.249m', 'min' => 2000000, 'max' => 2250000],
        ['label' => '$2.25m–$2.499m', 'min' => 2250000, 'max' => 2500000],
        ['label' => '$2.5m+', 'min' => 2500000, 'max' => null],
    ];

    public function __construct(
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
    ) {}

    /**
     * @return array{metric: string, scope: string, value: mixed}
     */
    public function metric(string $metric, ApplicationFilter $filter): array
    {
        $metric = strtolower($metric);

        if (! in_array($metric, self::METRICS, true)) {
            throw new InvalidArgumentException("Unknown metric [{$metric}].");
        }

        $value = match ($metric) {
            'applications' => $this->applicationsCount($filter),
            'estimated_costs' => $this->estimatedCosts($filter),
            'decision_duration' => $this->decisionDuration($filter),
            'application_types' => $this->groupedCount($filter, 'application_types', 'application_application_types', 'application_type_id', 'at'),
            'development_types' => $this->groupedCount($filter, 'development_types', 'application_development_types', 'development_type_id', 'dt'),
            'decision_classes' => $this->decisionClassCounts($filter),
        };

        return [
            'metric' => $metric,
            'scope' => $this->describeScope($filter),
            'value' => $value,
        ];
    }

    /**
     * Chart payloads always expose Chart.js-friendly `labels` + `values`.
     *
     * - categorical / bands: labels[], values[] (1D)
     * - calendar: labels[] = months, series[] = years, values[][] = matrix [year][month]
     * - timeseries: labels[] = periods, values[] = primary metric, series[] = full points
     *
     * @return array{
     *     metric: string,
     *     scope: string,
     *     format: string,
     *     interval: string|null,
     *     labels: list<string>,
     *     values: list<int|float>|list<list<int>>,
     *     series?: list<int>|list<array{period: string, count: int, sum?: float|null, avg?: float|null}>
     * }
     */
    public function chart(
        string $metric,
        ApplicationFilter $filter,
        string $interval = 'month',
        string $format = 'auto',
        int $limit = 9,
    ): array {
        $metric = strtolower($metric);

        if (! in_array($metric, self::CHART_METRICS, true)) {
            throw new InvalidArgumentException("Unknown chart metric [{$metric}].");
        }

        $format = strtolower($format);
        if ($format === 'auto') {
            $format = match ($metric) {
                'applications' => 'timeseries',
                'estimated_costs' => 'bands',
                default => 'categorical',
            };
        }

        $payload = match ($format) {
            'timeseries' => $this->timeseriesChart($metric, $filter, $interval),
            'calendar' => $this->calendarChart($filter),
            'categorical' => $this->categoricalChart($metric, $filter, $limit),
            'bands' => $this->costBandsChart($filter),
            default => throw new InvalidArgumentException("Unknown chart format [{$format}]."),
        };

        return [
            'metric' => $metric,
            'scope' => $this->describeScope($filter),
            'format' => $format,
            'interval' => $format === 'timeseries' ? $interval : null,
            ...$payload,
        ];
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     values: list<int|float>,
     *     series: list<array{period: string, count: int, sum?: float|null, avg?: float|null}>
     * }
     */
    private function timeseriesChart(string $metric, ApplicationFilter $filter, string $interval): array
    {
        if (! in_array($metric, ['applications', 'estimated_costs'], true)) {
            throw new InvalidArgumentException('Timeseries charts support applications and estimated_costs only.');
        }

        $trunc = match ($interval) {
            'day' => 'day',
            'week' => 'week',
            'year' => 'year',
            default => 'month',
        };

        $query = Application::query()->from('applications as a')
            ->whereNotNull('a.submitted')
            ->selectRaw('date_trunc(?, a.submitted)::date AS period', [$trunc])
            ->selectRaw('COUNT(DISTINCT a.id) AS count')
            ->groupBy('period')
            ->orderBy('period');

        if ($metric === 'estimated_costs') {
            $query->whereNotNull('a.estimated_cost')
                ->where('a.estimated_cost', '>', 0)
                ->selectRaw('SUM(a.estimated_cost) AS sum')
                ->selectRaw('AVG(a.estimated_cost) AS avg');
        }

        $this->applicationQuery->applyToApplications($query, $filter, 'a');

        $series = $query->get()->map(function ($row) use ($metric) {
            $point = [
                'period' => (string) $row->period,
                'count' => (int) $row->count,
            ];

            if ($metric === 'estimated_costs') {
                $point['sum'] = $row->sum !== null ? (float) $row->sum : null;
                $point['avg'] = $row->avg !== null ? round((float) $row->avg, 2) : null;
            }

            return $point;
        })->all();

        $labels = array_map(fn (array $point) => $point['period'], $series);
        $values = array_map(
            fn (array $point) => $metric === 'estimated_costs'
                ? (float) ($point['sum'] ?? 0)
                : (int) $point['count'],
            $series,
        );

        return [
            'labels' => $labels,
            'values' => $values,
            'series' => $series,
        ];
    }

    /**
     * Monthly matrix: labels = months, series = years, values = [year][month] counts.
     *
     * @return array{labels: list<string>, series: list<int>, values: list<list<int>>}
     */
    private function calendarChart(ApplicationFilter $filter): array
    {
        $from = $filter->submittedFrom?->copy() ?? Carbon::now()->subMonths(24)->startOfYear();
        $to = $filter->submittedTo?->copy() ?? Carbon::now();

        $scoped = new ApplicationFilter(
            bounds: $filter->bounds,
            applicationClassIds: $filter->applicationClassIds,
            developmentClassIds: $filter->developmentClassIds,
            decisionClassIds: $filter->decisionClassIds,
            estimatedCostMin: $filter->estimatedCostMin,
            estimatedCostMax: $filter->estimatedCostMax,
            submittedFrom: $from,
            submittedTo: $to,
            state: $filter->state,
            authorityId: $filter->authorityId,
            locationId: $filter->locationId,
            search: $filter->search,
            includeAmalgamated: $filter->includeAmalgamated,
            centerLat: $filter->centerLat,
            centerLng: $filter->centerLng,
            radiusMeters: $filter->radiusMeters,
            legislationIds: $filter->legislationIds,
            suburb: $filter->suburb,
            applicationTypeIds: $filter->applicationTypeIds,
            developmentTypeIds: $filter->developmentTypeIds,
            decisionTypeIds: $filter->decisionTypeIds,
            source: $filter->source,
        );

        $query = Application::query()->from('applications as a')
            ->whereNotNull('a.submitted')
            ->selectRaw("to_char(a.submitted, 'YYYY') AS year")
            ->selectRaw("to_char(a.submitted, 'FMMonth') AS month")
            ->selectRaw('COUNT(DISTINCT a.id) AS count')
            ->groupBy('year', 'month')
            ->orderBy('year');

        $this->applicationQuery->applyToApplications($query, $scoped, 'a');

        $rows = $query->get();
        $years = range((int) $from->year, (int) $to->year);
        $months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ];

        $values = [];
        foreach ($years as $year) {
            $yearData = [];
            foreach ($months as $month) {
                $match = $rows->first(
                    fn ($row) => (int) $row->year === $year && (string) $row->month === $month
                );
                $yearData[] = $match ? (int) $match->count : 0;
            }
            $values[] = $yearData;
        }

        return [
            'labels' => $months,
            'series' => $years,
            'values' => $values,
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function categoricalChart(string $metric, ApplicationFilter $filter, int $limit): array
    {
        $rows = match ($metric) {
            'application_types' => $this->groupedCount($filter, 'application_types', 'application_application_types', 'application_type_id', 'at', $limit),
            'development_types' => $this->groupedCount($filter, 'development_types', 'application_development_types', 'development_type_id', 'dt', $limit),
            'decision_classes' => array_slice($this->decisionClassCounts($filter), 0, max(1, $limit)),
            'applications' => throw new InvalidArgumentException('Use format=timeseries or format=calendar for applications.'),
            'estimated_costs' => throw new InvalidArgumentException('Use format=bands or format=timeseries for estimated_costs.'),
            default => throw new InvalidArgumentException("Unknown categorical metric [{$metric}]."),
        };

        return [
            'labels' => array_map(fn ($r) => (string) $r['name'], $rows),
            'values' => array_map(fn ($r) => (int) $r['count'], $rows),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function costBandsChart(ApplicationFilter $filter): array
    {
        $query = Application::query()->from('applications as a')
            ->whereNotNull('a.estimated_cost')
            ->where('a.estimated_cost', '>', 0)
            ->select(['a.id', 'a.estimated_cost']);

        $this->applicationQuery->applyToApplications($query, $filter, 'a');

        $costs = $query->get()->unique('id')->pluck('estimated_cost');

        $counts = array_fill(0, count(self::COST_BANDS), 0);

        foreach ($costs as $cost) {
            $cost = (float) $cost;
            foreach (self::COST_BANDS as $i => $band) {
                $inMin = $cost >= $band['min'];
                $inMax = $band['max'] === null ? true : $cost < $band['max'];
                if ($inMin && $inMax) {
                    $counts[$i]++;
                    break;
                }
            }
        }

        return [
            'labels' => array_column(self::COST_BANDS, 'label'),
            'values' => $counts,
        ];
    }

    private function applicationsCount(ApplicationFilter $filter): int
    {
        $query = Application::query()->from('applications as a');
        $this->applicationQuery->applyToApplications($query, $filter, 'a');

        return (int) $query->distinct('a.id')->count('a.id');
    }

    /**
     * @return array{count: int, sum: float|null, avg: float|null}
     */
    private function estimatedCosts(ApplicationFilter $filter): array
    {
        $query = Application::query()->from('applications as a')
            ->whereNotNull('a.estimated_cost');

        $this->applicationQuery->applyToApplications($query, $filter, 'a');

        $row = $query->selectRaw('COUNT(DISTINCT a.id) AS count')
            ->selectRaw('SUM(a.estimated_cost) AS sum')
            ->selectRaw('AVG(a.estimated_cost) AS avg')
            ->first();

        return [
            'count' => (int) ($row->count ?? 0),
            'sum' => $row->sum !== null ? (float) $row->sum : null,
            'avg' => $row->avg !== null ? round((float) $row->avg, 2) : null,
        ];
    }

    /**
     * Average days from submitted → decision for decided applications.
     *
     * @return array{count: int, avg: float|null}
     */
    private function decisionDuration(ApplicationFilter $filter): array
    {
        $query = Application::query()->from('applications as a')
            ->whereNotNull('a.submitted')
            ->whereNotNull('a.decision_date')
            ->whereColumn('a.decision_date', '>=', 'a.submitted');

        $this->applicationQuery->applyToApplications($query, $filter, 'a');

        $row = $query->selectRaw('COUNT(DISTINCT a.id) AS count')
            ->selectRaw('AVG((a.decision_date::date - a.submitted::date)) AS avg')
            ->first();

        return [
            'count' => (int) ($row->count ?? 0),
            'avg' => $row->avg !== null ? round((float) $row->avg, 1) : null,
        ];
    }

    /**
     * @return list<array{id: int, name: string, count: int}>
     */
    private function groupedCount(
        ApplicationFilter $filter,
        string $typesTable,
        string $pivotTable,
        string $pivotFk,
        string $alias,
        ?int $limit = null,
    ): array {
        $query = DB::connection(Application::query()->getConnection()->getName())
            ->table('applications as a')
            ->join("{$pivotTable} as p", 'p.application_id', '=', 'a.id')
            ->join("{$typesTable} as {$alias}", "{$alias}.id", '=', "p.{$pivotFk}")
            ->selectRaw("{$alias}.id, {$alias}.name, COUNT(DISTINCT a.id) AS count")
            ->groupBy("{$alias}.id", "{$alias}.name")
            ->orderByDesc('count');

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        $this->applicationQuery->applyToApplications($query, $filter, 'a');

        return $query->get()->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'count' => (int) $row->count,
        ])->all();
    }

    /**
     * @return list<array{id: int, name: string, count: int}>
     */
    private function decisionClassCounts(ApplicationFilter $filter): array
    {
        $query = DB::connection(Application::query()->getConnection()->getName())
            ->table('applications as a')
            ->join('application_decision_types as p', 'p.application_id', '=', 'a.id')
            ->join('decision_types as dt', 'dt.id', '=', 'p.decision_type_id')
            ->join('decision_classes as dc', 'dc.id', '=', 'dt.decision_class_id')
            ->selectRaw('dc.id, dc.name, COUNT(DISTINCT a.id) AS count')
            ->groupBy('dc.id', 'dc.name')
            ->orderByDesc('count');

        $this->applicationQuery->applyToApplications($query, $filter, 'a');

        return $query->get()->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'count' => (int) $row->count,
        ])->all();
    }

    private function describeScope(ApplicationFilter $filter): string
    {
        if ($filter->locationId !== null) {
            return 'location';
        }
        if ($filter->authorityId !== null) {
            return 'authority';
        }
        if ($filter->state !== null) {
            return 'state';
        }
        if ($filter->bounds !== null || ($filter->centerLat !== null && $filter->radiusMeters !== null)) {
            return 'map';
        }

        return 'all';
    }
}
