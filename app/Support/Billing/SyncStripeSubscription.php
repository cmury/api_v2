<?php

namespace App\Support\Billing;

use App\Models\User;
use Carbon\Carbon;
use Stripe\Subscription as StripeSubscription;
use Throwable;

/**
 * Sync Cashier subscription rows from Stripe when webhooks are delayed/missing
 * (common in local Test mode without `stripe listen`).
 */
final class SyncStripeSubscription
{
    /**
     * Pull subscriptions for the customer into local Cashier tables
     * (including cancel-at-period-end and fully canceled states).
     */
    public function syncCustomer(User $user): void
    {
        if (! $user->hasStripeId()) {
            return;
        }

        try {
            $subscriptions = $user->stripe()->subscriptions->all([
                'customer' => $user->stripe_id,
                'status' => 'all',
                'limit' => 20,
            ]);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        foreach ($subscriptions->data as $stripeSubscription) {
            if (! in_array($stripeSubscription->status, [
                StripeSubscription::STATUS_ACTIVE,
                StripeSubscription::STATUS_TRIALING,
                StripeSubscription::STATUS_PAST_DUE,
                StripeSubscription::STATUS_CANCELED,
                StripeSubscription::STATUS_UNPAID,
            ], true)) {
                continue;
            }

            $this->sync($user, $stripeSubscription);
        }
    }

    public function syncById(User $user, string $stripeSubscriptionId): void
    {
        try {
            $stripeSubscription = $user->stripe()->subscriptions->retrieve($stripeSubscriptionId);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        $this->sync($user, $stripeSubscription);
    }

    public function sync(User $user, StripeSubscription $stripeSubscription): void
    {
        $items = $stripeSubscription->items->data ?? [];
        if ($items === []) {
            return;
        }

        $firstItem = $items[0];
        $isSinglePrice = count($items) === 1;

        $type = $stripeSubscription->metadata['type']
            ?? $stripeSubscription->metadata['name']
            ?? BillingPlans::SUBSCRIPTION_TYPE;

        // Always prefer the app subscription type when metadata is missing/default.
        if ($type === 'default' || $type === '') {
            $type = BillingPlans::SUBSCRIPTION_TYPE;
        }

        $trialEndsAt = isset($stripeSubscription->trial_end)
            ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
            : null;

        $subscription = $user->subscriptions()->updateOrCreate([
            'stripe_id' => $stripeSubscription->id,
        ], [
            'type' => $type,
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => $isSinglePrice ? $firstItem->price->id : null,
            'quantity' => $isSinglePrice ? ($firstItem->quantity ?? null) : null,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => $this->resolveEndsAt($stripeSubscription, $trialEndsAt),
        ]);

        foreach ($items as $item) {
            $subscription->items()->updateOrCreate([
                'stripe_id' => $item->id,
            ], [
                'stripe_product' => $item->price->product,
                'stripe_price' => $item->price->id,
                'quantity' => $item->quantity ?? null,
            ]);
        }

        if (! is_null($user->trial_ends_at)) {
            $user->trial_ends_at = null;
            $user->save();
        }
    }

    private function resolveEndsAt(StripeSubscription $stripeSubscription, ?Carbon $trialEndsAt): ?Carbon
    {
        // Still active/trialing but set to cancel at period end → grace period.
        if ($stripeSubscription->cancel_at_period_end) {
            if ($trialEndsAt !== null && $trialEndsAt->isFuture()) {
                return $trialEndsAt;
            }

            if (isset($stripeSubscription->cancel_at)) {
                return Carbon::createFromTimestamp($stripeSubscription->cancel_at);
            }

            if (isset($stripeSubscription->current_period_end)) {
                return Carbon::createFromTimestamp($stripeSubscription->current_period_end);
            }
        }

        if (isset($stripeSubscription->cancel_at) && $stripeSubscription->status === StripeSubscription::STATUS_CANCELED) {
            return Carbon::createFromTimestamp($stripeSubscription->cancel_at);
        }

        if (isset($stripeSubscription->canceled_at) && $stripeSubscription->status === StripeSubscription::STATUS_CANCELED) {
            return Carbon::createFromTimestamp($stripeSubscription->canceled_at);
        }

        if ($stripeSubscription->status === StripeSubscription::STATUS_CANCELED) {
            return isset($stripeSubscription->ended_at)
                ? Carbon::createFromTimestamp($stripeSubscription->ended_at)
                : now();
        }

        // Resumed / active with no pending cancellation.
        return null;
    }
}
