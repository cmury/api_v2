<?php

namespace App\Support\Billing;

use InvalidArgumentException;

final class BillingPlans
{
    public const SUBSCRIPTION_TYPE = 'main';

    /**
     * @return array<string, array{price_id: string, name: string, description: string, amount_display: string}>
     */
    public function all(): array
    {
        /** @var array<string, array{price_id?: string|null, name?: string, description?: string, amount_display?: string}> $plans */
        $plans = config('imby.billing.plans', []);

        $configured = [];

        foreach ($plans as $key => $plan) {
            $priceId = $plan['price_id'] ?? null;

            if (! is_string($priceId) || $priceId === '') {
                continue;
            }

            $configured[$key] = [
                'price_id' => $priceId,
                'name' => (string) ($plan['name'] ?? $key),
                'description' => (string) ($plan['description'] ?? ''),
                'amount_display' => (string) ($plan['amount_display'] ?? ''),
            ];
        }

        return $configured;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return list<string>
     */
    public function priceIds(): array
    {
        return array_values(array_map(
            static fn (array $plan): string => $plan['price_id'],
            $this->all(),
        ));
    }

    public function resolvePriceId(string $planOrPriceId): string
    {
        $plans = $this->all();

        if (isset($plans[$planOrPriceId])) {
            return $plans[$planOrPriceId]['price_id'];
        }

        foreach ($plans as $plan) {
            if ($plan['price_id'] === $planOrPriceId) {
                return $planOrPriceId;
            }
        }

        throw new InvalidArgumentException("Unknown billing plan [{$planOrPriceId}].");
    }

    public function keyForPriceId(?string $priceId): ?string
    {
        if ($priceId === null || $priceId === '') {
            return null;
        }

        foreach ($this->all() as $key => $plan) {
            if ($plan['price_id'] === $priceId) {
                return $key;
            }
        }

        return null;
    }

    public function isConfigured(): bool
    {
        return $this->all() !== [];
    }
}
