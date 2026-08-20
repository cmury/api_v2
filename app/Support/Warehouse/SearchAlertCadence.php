<?php

namespace App\Support\Warehouse;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * When a saved-search alert is due. Frequency is send cadence, not the map date window.
 */
final class SearchAlertCadence
{
    public function frequencyFor(User $user): string
    {
        $frequency = $user->preferences?->notification_frequency
            ?? config('imby.default_notification_frequency', 'weekly');

        return is_string($frequency) && $frequency !== '' ? $frequency : 'weekly';
    }

    public function intervalDays(string $frequency): ?int
    {
        return match ($frequency) {
            'never' => null,
            'weekly' => 7,
            'fortnightly' => 15,
            'monthly' => 30,
            'immediately', 'daily' => 1,
            default => 1,
        };
    }

    /**
     * immediately is always due to check (send only when there are matches).
     * Other cadences are due when never notified, or when last_notified_at is old enough.
     */
    public function isDue(string $frequency, mixed $lastNotifiedAt, ?CarbonInterface $now = null): bool
    {
        if ($frequency === 'never' || $this->intervalDays($frequency) === null) {
            return false;
        }

        if ($frequency === 'immediately') {
            return true;
        }

        if ($lastNotifiedAt === null || $lastNotifiedAt === '') {
            return true;
        }

        $now ??= Carbon::now();
        $days = $this->intervalDays($frequency) ?? 1;

        return Carbon::parse($lastNotifiedAt)->lte($now->copy()->subDays($days));
    }
}
