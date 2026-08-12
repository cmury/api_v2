<?php

namespace App\Support\Billing;

final class PlanEntitlements
{
    public const FEATURE_PLANNING_LAYERS = 'planning-layers';

    public const FEATURE_ANALYTICS = 'analytics';

    /** @var array<string, int> */
    private const RANK = [
        'free' => 0,
        'core' => 1,
        'pro' => 2,
        'business' => 3,
        'enterprise' => 4,
    ];

    /** @var array<string, string> */
    private const FEATURE_MIN_PLAN = [
        self::FEATURE_PLANNING_LAYERS => 'core',
        self::FEATURE_ANALYTICS => 'pro',
    ];

    public static function allows(string $plan, string $feature): bool
    {
        $minimum = self::FEATURE_MIN_PLAN[$feature] ?? null;
        if ($minimum === null) {
            return false;
        }

        return (self::RANK[$plan] ?? 0) >= (self::RANK[$minimum] ?? PHP_INT_MAX);
    }

    public static function minimumPlan(string $feature): ?string
    {
        return self::FEATURE_MIN_PLAN[$feature] ?? null;
    }
}
