<?php

namespace Tests\Unit;

use App\Support\Warehouse\ForecastQuery;
use Tests\TestCase;

class ForecastQueryTest extends TestCase
{
    public function test_project_series_uses_seasonal_history(): void
    {
        $history = [
            '2024-01' => 100,
            '2024-02' => 80,
            '2025-01' => 120,
            '2025-02' => 90,
            '2025-11' => 110,
            '2025-12' => 105,
        ];

        $projection = (new ForecastQuery)->projectSeries($history, ['2026-01', '2026-02']);

        $this->assertCount(2, $projection);
        $this->assertSame('2026-01', $projection[0]['period']);
        $this->assertSame('2026-02', $projection[1]['period']);

        // January seasonal avg ~110, recent avg of last 3 months ~101.67 → ~107–108
        $this->assertGreaterThan(90, $projection[0]['point']);
        $this->assertLessThan(130, $projection[0]['point']);
        $this->assertGreaterThanOrEqual(0, $projection[0]['low']);
        $this->assertLessThanOrEqual($projection[0]['high'], $projection[0]['point']); // point <= high
        $this->assertLessThanOrEqual($projection[0]['point'], $projection[0]['low']); // low <= point
    }

    public function test_project_series_handles_empty_history(): void
    {
        $projection = (new ForecastQuery)->projectSeries([], ['2026-08']);

        $this->assertSame([
            [
                'period' => '2026-08',
                'point' => 0,
                'low' => 0,
                'high' => 0,
            ],
        ], $projection);
    }
}
