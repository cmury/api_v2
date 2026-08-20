<?php

namespace Tests\Feature;

use App\Support\Warehouse\SearchAlertDispatcher;
use Tests\TestCase;

class SendSearchAlertsCommandTest extends TestCase
{
    public function test_command_passes_dry_run_and_user_to_dispatcher(): void
    {
        $this->mock(SearchAlertDispatcher::class, function ($mock): void {
            $mock->shouldReceive('run')
                ->once()
                ->with(true, 42)
                ->andReturn(['sent' => 1, 'empty' => 2, 'skipped' => 3, 'failed' => 0]);
        });

        $this->artisan('notifications:send-search-alerts', [
            '--dry-run' => true,
            '--user' => 42,
        ])
            ->expectsOutputToContain('sent 1, empty 2, skipped 3, failed 0')
            ->assertSuccessful();
    }

    public function test_command_fails_when_dispatcher_reports_failures(): void
    {
        $this->mock(SearchAlertDispatcher::class, function ($mock): void {
            $mock->shouldReceive('run')
                ->once()
                ->with(false, null)
                ->andReturn(['sent' => 0, 'empty' => 0, 'skipped' => 0, 'failed' => 1]);
        });

        $this->artisan('notifications:send-search-alerts')->assertFailed();
    }
}
