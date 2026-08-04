<?php

namespace App\Support\Reports;

use App\Models\Location;
use App\Models\ReportPurchase;
use Illuminate\Support\Str;
use Throwable;

final class PropertyReportCheckout
{
    public function __construct(
        private readonly StripePaymentIntents $paymentIntents,
    ) {}

    /**
     * @param  array{location_id?: int|null, lat?: float|null, lng?: float|null, address?: string|null, email?: string|null}  $input
     * @return array{purchase: ReportPurchase, client_secret: string, publishable_key: string|null}
     */
    public function start(array $input): array
    {
        $amount = (int) config('imby.reports.property.amount_cents', 2900);
        $currency = strtolower((string) config('imby.reports.property.currency', config('cashier.currency', 'aud')));
        $locationId = isset($input['location_id']) ? (int) $input['location_id'] : null;
        $lat = isset($input['lat']) ? (float) $input['lat'] : null;
        $lng = isset($input['lng']) ? (float) $input['lng'] : null;
        $address = isset($input['address']) ? trim((string) $input['address']) : null;
        $email = isset($input['email']) ? trim((string) $input['email']) : null;

        if ($locationId) {
            $location = Location::query()->findOrFail($locationId);
            $address = $address ?: $location->formatted_address;

            if ($lat === null || $lng === null) {
                try {
                    $coords = Location::query()
                        ->whereKey($location->id)
                        ->whereNotNull('geom')
                        ->selectRaw('ST_Y(geom::geometry) AS lat, ST_X(geom::geometry) AS lng')
                        ->first();
                    if ($coords) {
                        $lat = (float) $coords->lat;
                        $lng = (float) $coords->lng;
                    }
                } catch (Throwable) {
                    // Optional — payment can proceed without coords.
                }
            }
        }

        $token = Str::random(48);
        $purchase = ReportPurchase::query()->create([
            'type' => ReportPurchase::TYPE_PROPERTY,
            'location_id' => $locationId,
            'lat' => $lat,
            'lng' => $lng,
            'formatted_address' => $address,
            'amount_cents' => $amount,
            'currency' => $currency,
            'status' => ReportPurchase::STATUS_PENDING,
            'download_token' => $token,
            'email' => $email ?: null,
            'expires_at' => now()->addHours((int) config('imby.reports.property.pending_ttl_hours', 24)),
            'metadata' => [
                'product' => 'property_report',
            ],
        ]);

        $intent = $this->paymentIntents->create([
            'amount' => $amount,
            'currency' => $currency,
            'automatic_payment_methods' => ['enabled' => true],
            'receipt_email' => $email ?: null,
            'metadata' => [
                'report_purchase_id' => (string) $purchase->id,
                'download_token' => $token,
                'product' => 'property_report',
            ],
            'description' => 'IMBY Property Report'.($address ? ' — '.$address : ''),
        ]);

        $purchase->forceFill([
            'stripe_payment_intent_id' => $intent->id,
        ])->save();

        return [
            'purchase' => $purchase->fresh(),
            'client_secret' => (string) $intent->client_secret,
            'publishable_key' => config('cashier.key'),
        ];
    }

    public function syncFromStripe(ReportPurchase $purchase): ReportPurchase
    {
        if ($purchase->isPaid() || ! $purchase->stripe_payment_intent_id) {
            return $purchase;
        }

        $intent = $this->paymentIntents->retrieve($purchase->stripe_payment_intent_id);

        if ($intent->status === 'succeeded') {
            $purchase->markPaid();
        } elseif (in_array($intent->status, ['canceled', 'cancelled'], true)) {
            $purchase->forceFill(['status' => ReportPurchase::STATUS_FAILED])->save();
        }

        return $purchase->fresh();
    }

    public function markPaidByPaymentIntent(string $paymentIntentId): ?ReportPurchase
    {
        $purchase = ReportPurchase::query()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->first();

        if ($purchase === null) {
            return null;
        }

        if (! $purchase->isPaid()) {
            $purchase->markPaid();
        }

        return $purchase->fresh();
    }
}
