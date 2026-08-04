<?php

namespace Tests\Feature;

use App\Models\ReportPurchase;
use App\Support\Reports\StripePaymentIntents;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Stripe\PaymentIntent;
use Tests\TestCase;

class PropertyReportEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'imby.reports.property.amount_cents' => 2900,
            'imby.reports.property.currency' => 'aud',
            'cashier.key' => 'pk_test_example',
            'database.connections.data' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'database.data_connection' => 'data',
        ]);

        Schema::connection('data')->create('report_purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->default('property');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('formatted_address')->nullable();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('aud');
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('status')->default('pending');
            $table->string('download_token', 64)->unique();
            $table->string('email')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_pricing_is_public(): void
    {
        $this->getJson('/api/reports/property/pricing')
            ->assertOk()
            ->assertJsonPath('data.amount_cents', 2900)
            ->assertJsonPath('data.currency', 'aud')
            ->assertJsonPath('data.publishable_key', 'pk_test_example');
    }

    public function test_example_pdf_is_public(): void
    {
        $response = $this->get('/api/reports/property/example');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_pay_validates_property_reference(): void
    {
        $this->postJson('/api/reports/property/pay', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('location_id');
    }

    public function test_pay_creates_payment_intent_and_purchase(): void
    {
        $fake = Mockery::mock(StripePaymentIntents::class);
        $fake->shouldReceive('create')
            ->once()
            ->andReturn(PaymentIntent::constructFrom([
                'id' => 'pi_test_report_1',
                'client_secret' => 'pi_test_report_1_secret_xyz',
                'status' => 'requires_payment_method',
            ]));
        $this->app->instance(StripePaymentIntents::class, $fake);

        $this->postJson('/api/reports/property/pay', [
            'address' => '12 Example Street, Surry Hills NSW 2010',
            'lat' => -33.8837,
            'lng' => 151.2110,
            'email' => 'buyer@example.com',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'payment_intent_created')
            ->assertJsonPath('data.client_secret', 'pi_test_report_1_secret_xyz')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('report_purchases', [
            'stripe_payment_intent_id' => 'pi_test_report_1',
            'status' => 'pending',
            'email' => 'buyer@example.com',
        ], 'data');
    }

    public function test_download_requires_paid_status(): void
    {
        $purchase = ReportPurchase::query()->create([
            'type' => 'property',
            'formatted_address' => '12 Example Street',
            'amount_cents' => 2900,
            'currency' => 'aud',
            'status' => ReportPurchase::STATUS_PENDING,
            'download_token' => str_repeat('a', 48),
            'stripe_payment_intent_id' => 'pi_pending',
        ]);

        $fake = Mockery::mock(StripePaymentIntents::class);
        $fake->shouldReceive('retrieve')
            ->once()
            ->with('pi_pending')
            ->andReturn(PaymentIntent::constructFrom([
                'id' => 'pi_pending',
                'status' => 'requires_payment_method',
            ]));
        $this->app->instance(StripePaymentIntents::class, $fake);

        $this->getJson('/api/reports/property/'.$purchase->download_token.'/download')
            ->assertStatus(402)
            ->assertJsonPath('message', 'payment_required');
    }

    public function test_download_returns_pdf_when_paid(): void
    {
        $purchase = ReportPurchase::query()->create([
            'type' => 'property',
            'formatted_address' => '12 Example Street, Surry Hills NSW 2010',
            'lat' => -33.8837,
            'lng' => 151.2110,
            'amount_cents' => 2900,
            'currency' => 'aud',
            'status' => ReportPurchase::STATUS_PAID,
            'download_token' => str_repeat('b', 48),
            'paid_at' => now(),
            'stripe_payment_intent_id' => 'pi_paid',
        ]);

        $response = $this->get('/api/reports/property/'.$purchase->download_token.'/download');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_status_marks_paid_when_stripe_succeeded(): void
    {
        $purchase = ReportPurchase::query()->create([
            'type' => 'property',
            'formatted_address' => '12 Example Street',
            'amount_cents' => 2900,
            'currency' => 'aud',
            'status' => ReportPurchase::STATUS_PENDING,
            'download_token' => str_repeat('c', 48),
            'stripe_payment_intent_id' => 'pi_sync',
        ]);

        $fake = Mockery::mock(StripePaymentIntents::class);
        $fake->shouldReceive('retrieve')
            ->once()
            ->with('pi_sync')
            ->andReturn(PaymentIntent::constructFrom([
                'id' => 'pi_sync',
                'status' => 'succeeded',
            ]));
        $this->app->instance(StripePaymentIntents::class, $fake);

        $this->getJson('/api/reports/property/'.$purchase->download_token.'/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.download_ready', true);
    }
}
