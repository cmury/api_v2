<?php

namespace App\Support\Warehouse;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Resolves relative / custom submitted-date windows used by map filters.
 *
 * Accepts the old API shape: { type, start?, end? } where type is e.g.
 * "last 30 days", "this month", "custom".
 */
final class DateRange
{
    /**
     * @param  array{type?: string, start?: mixed, end?: mixed}|null  $input
     * @return array{0: CarbonInterface|null, 1: CarbonInterface|null}
     */
    public static function resolve(?array $input, ?int $defaultDays = null): array
    {
        if ($input === null || $input === []) {
            if ($defaultDays === null) {
                return [null, null];
            }

            $end = Carbon::now()->endOfDay();
            $start = Carbon::now()->subDays($defaultDays)->startOfDay();

            return [$start, $end];
        }

        $type = strtolower(trim((string) ($input['type'] ?? 'custom')));

        if ($type === '' || $type === 'all') {
            return [null, null];
        }

        $today = Carbon::now();

        return match ($type) {
            'this week' => [$today->copy()->startOfWeek(), $today->copy()->endOfDay()],
            'last week' => [
                $today->copy()->subWeek()->startOfWeek(),
                $today->copy()->subWeek()->endOfWeek(),
            ],
            'this month' => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
            'last month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this quarter' => [$today->copy()->firstOfQuarter(), $today->copy()->endOfDay()],
            'last quarter' => [
                $today->copy()->subQuarter()->firstOfQuarter(),
                $today->copy()->subQuarter()->lastOfQuarter(),
            ],
            'this year' => [$today->copy()->startOfYear(), $today->copy()->endOfDay()],
            'last year' => [
                $today->copy()->subYear()->startOfYear(),
                $today->copy()->subYear()->endOfYear(),
            ],
            'last 7 days' => [$today->copy()->subDays(7)->startOfDay(), $today->copy()->endOfDay()],
            'last 14 days' => [$today->copy()->subDays(14)->startOfDay(), $today->copy()->endOfDay()],
            'last 30 days' => [$today->copy()->subDays(30)->startOfDay(), $today->copy()->endOfDay()],
            'last 90 days' => [$today->copy()->subDays(90)->startOfDay(), $today->copy()->endOfDay()],
            'last 180 days' => [$today->copy()->subDays(180)->startOfDay(), $today->copy()->endOfDay()],
            'last 365 days' => [$today->copy()->subDays(365)->startOfDay(), $today->copy()->endOfDay()],
            'last 730 days' => [$today->copy()->subDays(730)->startOfDay(), $today->copy()->endOfDay()],
            'last 1095 days' => [$today->copy()->subDays(1095)->startOfDay(), $today->copy()->endOfDay()],
            'custom' => self::custom($input),
            default => throw new InvalidArgumentException("Unknown date range type [{$type}]."),
        };
    }

    /**
     * @param  array{start?: mixed, end?: mixed}  $input
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private static function custom(array $input): array
    {
        if (empty($input['start']) || empty($input['end'])) {
            throw new InvalidArgumentException('Custom date ranges require start and end.');
        }

        return [
            Carbon::parse($input['start'])->startOfDay(),
            Carbon::parse($input['end'])->endOfDay(),
        ];
    }
}
