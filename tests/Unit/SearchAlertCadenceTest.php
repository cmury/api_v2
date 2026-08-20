<?php

namespace Tests\Unit;

use App\Support\Warehouse\SearchAlertCadence;
use Carbon\Carbon;
use Tests\TestCase;

class SearchAlertCadenceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_never_is_not_due(): void
    {
        $cadence = new SearchAlertCadence;

        $this->assertFalse($cadence->isDue('never', null));
        $this->assertNull($cadence->intervalDays('never'));
    }

    public function test_immediately_is_always_due(): void
    {
        Carbon::setTestNow('2026-08-20 12:00:00');

        $cadence = new SearchAlertCadence;

        $this->assertTrue($cadence->isDue('immediately', null));
        $this->assertTrue($cadence->isDue('immediately', '2026-08-20 11:59:00'));
    }

    public function test_weekly_due_when_never_notified_or_interval_elapsed(): void
    {
        Carbon::setTestNow('2026-08-20 12:00:00');

        $cadence = new SearchAlertCadence;

        $this->assertTrue($cadence->isDue('weekly', null));
        $this->assertTrue($cadence->isDue('weekly', '2026-08-13 12:00:00'));
        $this->assertFalse($cadence->isDue('weekly', '2026-08-14 12:00:00'));
        $this->assertSame(7, $cadence->intervalDays('weekly'));
        $this->assertSame(15, $cadence->intervalDays('fortnightly'));
        $this->assertSame(30, $cadence->intervalDays('monthly'));
        $this->assertSame(1, $cadence->intervalDays('daily'));
    }
}
