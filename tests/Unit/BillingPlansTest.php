<?php

namespace Tests\Unit;

use App\Support\Billing\BillingPlans;
use Tests\TestCase;

class BillingPlansTest extends TestCase
{
    public function test_resolves_plan_keys_and_price_ids(): void
    {
        config([
            'imby.billing.plans' => [
                'core' => [
                    'price_id' => 'price_test_core',
                    'name' => 'Core',
                    'description' => 'Core plan',
                    'amount_display' => '$9 / month',
                ],
                'empty' => [
                    'price_id' => null,
                    'name' => 'Empty',
                ],
            ],
        ]);

        $plans = app(BillingPlans::class);

        $this->assertSame(['core'], $plans->keys());
        $this->assertSame('price_test_core', $plans->resolvePriceId('core'));
        $this->assertSame('price_test_core', $plans->resolvePriceId('price_test_core'));
        $this->assertSame('core', $plans->keyForPriceId('price_test_core'));
        $this->assertNull($plans->keyForPriceId('price_unknown'));
        $this->assertTrue($plans->isConfigured());
    }

    public function test_is_not_configured_when_no_price_ids(): void
    {
        config(['imby.billing.plans' => [
            'core' => ['price_id' => '', 'name' => 'Core'],
        ]]);

        $this->assertFalse(app(BillingPlans::class)->isConfigured());
    }
}
