<?php

namespace App\Console\Commands;

use App\Support\Warehouse\SearchAlertDispatcher;
use Illuminate\Console\Command;

class SendSearchAlertsCommand extends Command
{
    protected $signature = 'notifications:send-search-alerts
                            {--dry-run : Match and report without sending mail or stamping last_notified_at}
                            {--user= : Only this users.id}';

    protected $description = 'Email new-to-IMBY applications for notify-enabled saved searches';

    public function handle(SearchAlertDispatcher $dispatcher): int
    {
        $userId = $this->option('user');
        $userId = $userId === null || $userId === '' ? null : (int) $userId;

        $counts = $dispatcher->run(
            dryRun: (bool) $this->option('dry-run'),
            userId: $userId,
        );

        $suffix = $this->option('dry-run') ? ' (dry-run)' : '';

        $this->info(sprintf(
            'Search alerts: sent %d, empty %d, skipped %d, failed %d%s',
            $counts['sent'],
            $counts['empty'],
            $counts['skipped'],
            $counts['failed'],
            $suffix,
        ));

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
