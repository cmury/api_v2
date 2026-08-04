<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Billing\BillingPlans;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'imby.billing.plans' => [
                'core' => [
                    'price_id' => 'price_test_core',
                    'name' => 'Core',
                    'description' => 'Core plan',
                    'amount_display' => '$9 / month',
                ],
                'pro' => [
                    'price_id' => 'price_test_pro',
                    'name' => 'Pro',
                    'description' => 'Pro plan',
                    'amount_display' => '$19 / month',
                ],
            ],
            'imby.billing.trial_days' => 14,
        ]);
    }

    public function test_plans_is_public_and_lists_configured_prices(): void
    {
        $this->getJson('/api/billing/plans')
            ->assertOk()
            ->assertJsonPath('message', 'billing_plans')
            ->assertJsonPath('data.subscription_type', BillingPlans::SUBSCRIPTION_TYPE)
            ->assertJsonPath('data.plans.0.key', 'core')
            ->assertJsonPath('data.plans.0.price_id', 'price_test_core')
            ->assertJsonPath('data.plans.1.key', 'pro');
    }

    public function test_status_requires_authentication(): void
    {
        $this->getJson('/api/billing/status')->assertUnauthorized();
    }

    public function test_checkout_requires_authentication(): void
    {
        $this->postJson('/api/billing/checkout', [
            'plan' => 'core',
            'success_url' => 'https://app.example/success',
            'cancel_url' => 'https://app.example/cancel',
        ])->assertUnauthorized();
    }

    public function test_checkout_validates_plan_and_urls(): void
    {
        Sanctum::actingAs(new User(['email' => 'billing@example.com']));

        $this->postJson('/api/billing/checkout', [
            'plan' => 'not-a-plan',
            'success_url' => 'not-a-url',
            'cancel_url' => 'also-bad',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plan', 'success_url', 'cancel_url']);
    }

    public function test_portal_requires_authentication_and_return_url(): void
    {
        $this->postJson('/api/billing/portal', [])->assertUnauthorized();

        Sanctum::actingAs(new User(['email' => 'billing@example.com']));

        $this->postJson('/api/billing/portal', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('return_url');
    }

    public function test_portal_rejects_users_without_stripe_customer(): void
    {
        Sanctum::actingAs(new User(['email' => 'billing@example.com']));

        $this->postJson('/api/billing/portal', [
            'return_url' => 'https://app.example/account',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'no_stripe_customer');
    }

    public function test_swap_requires_authentication_and_valid_plan(): void
    {
        $this->postJson('/api/billing/swap', ['plan' => 'pro'])->assertUnauthorized();

        Sanctum::actingAs(new User(['email' => 'billing@example.com']));

        $this->postJson('/api/billing/swap', ['plan' => 'missing'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');
    }

    public function test_confirm_requires_authentication_and_session_id(): void
    {
        $this->postJson('/api/billing/confirm', [])->assertUnauthorized();

        Sanctum::actingAs(new User(['email' => 'billing@example.com']));

        $this->postJson('/api/billing/confirm', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('session_id');
    }

    public function test_cancel_and_resume_require_authentication(): void
    {
        $this->postJson('/api/billing/cancel')->assertUnauthorized();
        $this->postJson('/api/billing/resume')->assertUnauthorized();
    }
}
