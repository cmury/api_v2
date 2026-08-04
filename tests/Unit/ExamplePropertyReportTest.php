<?php

namespace Tests\Unit;

use App\Support\Reports\ExamplePropertyReport;
use Tests\TestCase;

class ExamplePropertyReportTest extends TestCase
{
    public function test_example_dataset_has_required_sections(): void
    {
        $data = ExamplePropertyReport::data();

        $this->assertTrue($data['is_example']);
        $this->assertNotEmpty($data['property']['address']);
        $this->assertNotEmpty($data['planning_controls']);
        $this->assertNotEmpty($data['applications']);
        $this->assertSame(4, $data['summary']['planning_control_count']);
    }
}
