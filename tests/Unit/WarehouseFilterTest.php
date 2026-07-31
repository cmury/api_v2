<?php

namespace Tests\Unit;

use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\DateRange;
use App\Support\Warehouse\GeoJson;
use App\Support\Warehouse\StatsQuery;
use Carbon\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class WarehouseFilterTest extends TestCase
{
    public function test_date_range_resolves_last_30_days(): void
    {
        Carbon::setTestNow('2026-07-26 12:00:00');

        [$from, $to] = DateRange::resolve(['type' => 'last 30 days']);

        $this->assertSame('2026-06-26', $from->toDateString());
        $this->assertSame('2026-07-26', $to->toDateString());

        Carbon::setTestNow();
    }

    public function test_date_range_custom_requires_bounds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateRange::resolve(['type' => 'custom']);
    }

    public function test_application_filter_accepts_legacy_map_query_keys(): void
    {
        $filter = ApplicationFilter::fromArray([
            'app' => [1, 2],
            'type' => [3],
            'status' => [4],
            'estvalue' => ['low' => 1000, 'high' => 5000],
            'map' => ['bounds' => [-33.1, 151.2, -34.0, 150.5]],
            'date' => ['type' => 'custom', 'start' => '2024-01-01', 'end' => '2024-12-31'],
        ]);

        $this->assertSame([1, 2], $filter->applicationClassIds);
        $this->assertSame([3], $filter->developmentClassIds);
        $this->assertSame([4], $filter->decisionClassIds);
        $this->assertSame(1000.0, $filter->estimatedCostMin);
        $this->assertSame(5000.0, $filter->estimatedCostMax);
        $this->assertSame([-33.1, 151.2, -34.0, 150.5], $filter->bounds);
        $this->assertSame('2024-01-01', $filter->submittedFrom?->toDateString());
        $this->assertSame('2024-12-31', $filter->submittedTo?->toDateString());
    }

    public function test_geojson_strips_australia_suffix(): void
    {
        $this->assertSame(
            '45 Palmer St, Balmain NSW 2041',
            GeoJson::stripCountry('45 Palmer St, Balmain NSW 2041, Australia'),
        );
    }

    public function test_cost_bands_cover_one_million_correctly(): void
    {
        $bands = StatsQuery::COST_BANDS;

        $oneMillion = 1_000_000.0;
        $matched = null;
        foreach ($bands as $band) {
            $inMin = $oneMillion >= $band['min'];
            $inMax = $band['max'] === null ? true : $oneMillion < $band['max'];
            if ($inMin && $inMax) {
                $matched = $band['label'];
                break;
            }
        }

        $this->assertSame('$1.0m–$1.249m', $matched);
    }

    public function test_application_filter_accepts_legislation_ids(): void
    {
        $filter = ApplicationFilter::fromArray([
            'legislation_ids' => [10, 20],
        ]);

        $this->assertSame([10, 20], $filter->legislationIds);
    }

    public function test_application_filter_accepts_type_ids(): void
    {
        $filter = ApplicationFilter::fromArray([
            'application_type_ids' => [1, 2],
            'development_type_ids' => [3],
            'decision_type_ids' => [4, 5],
        ]);

        $this->assertSame([1, 2], $filter->applicationTypeIds);
        $this->assertSame([3], $filter->developmentTypeIds);
        $this->assertSame([4, 5], $filter->decisionTypeIds);
    }

    public function test_application_filter_accepts_source(): void
    {
        $filter = ApplicationFilter::fromArray([
            'source' => 'act-dafinder',
        ]);

        $this->assertSame('act-dafinder', $filter->source);
    }

    public function test_chart_rejects_unknown_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown chart format');

        (new StatsQuery)->chart('applications', new ApplicationFilter, 'month', 'heatmap');
    }

    public function test_chart_rejects_categorical_for_applications(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('format=timeseries or format=calendar');

        (new StatsQuery)->chart('applications', new ApplicationFilter, 'month', 'categorical');
    }

    public function test_chart_rejects_timeseries_for_application_types(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeseries charts support applications and estimated_costs only.');

        (new StatsQuery)->chart('application_types', new ApplicationFilter, 'month', 'timeseries');
    }
}
