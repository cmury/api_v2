<?php

namespace App\Support\Reports;

use Laravel\Cashier\Cashier;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

/**
 * Thin Stripe PaymentIntent wrapper so tests can bind a fake.
 */
class StripePaymentIntents
{
    public function client(): StripeClient
    {
        return Cashier::stripe();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function create(array $params): PaymentIntent
    {
        return $this->client()->paymentIntents->create($params);
    }

    public function retrieve(string $id): PaymentIntent
    {
        return $this->client()->paymentIntents->retrieve($id);
    }
}
