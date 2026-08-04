<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CheckoutRequest;
use App\Http\Requests\Billing\PortalRequest;
use App\Http\Requests\Billing\SwapPlanRequest;
use App\Models\User;
use App\Support\Billing\BillingPlans;
use App\Support\Billing\SyncStripeSubscription;
use App\Support\UserActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Throwable;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingPlans $plans,
        private readonly UserActivityLogger $activityLogger,
        private readonly SyncStripeSubscription $subscriptionSync,
    ) {}

    public function plans(): JsonResponse
    {
        $plans = [];

        foreach ($this->plans->all() as $key => $plan) {
            $plans[] = [
                'key' => $key,
                ...$plan,
            ];
        }

        return response()->json([
            'message' => 'billing_plans',
            'data' => [
                'currency' => config('cashier.currency'),
                'subscription_type' => BillingPlans::SUBSCRIPTION_TYPE,
                'trial_days' => (int) config('imby.billing.trial_days', 0),
                'plans' => $plans,
            ],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Without webhooks, Stripe is the source of truth for subscribe + cancel.
        if ($user->hasStripeId()) {
            $this->subscriptionSync->syncCustomer($user);
            $user->refresh();
            $user->unsetRelation('subscriptions');
            $user->load('subscriptions');
        }

        return response()->json([
            'message' => 'billing_status',
            'data' => $this->statusPayload($user),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
        ]);

        try {
            $session = $user->stripe()->checkout->sessions->retrieve($validated['session_id']);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'checkout_session_not_found',
                'errors' => [
                    'session_id' => ['Unable to retrieve the Checkout session from Stripe.'],
                ],
            ], 404);
        }

        if ($user->hasStripeId() && $session->customer && $session->customer !== $user->stripe_id) {
            return response()->json([
                'message' => 'checkout_session_mismatch',
                'errors' => [
                    'session_id' => ['This Checkout session does not belong to the current user.'],
                ],
            ], 403);
        }

        if (is_string($session->subscription) && $session->subscription !== '') {
            $this->subscriptionSync->syncById($user, $session->subscription);
        } elseif ($user->hasStripeId()) {
            $this->subscriptionSync->syncCustomer($user);
        }

        $user->refresh();
        $user->load('subscriptions');

        return response()->json([
            'message' => 'checkout_confirmed',
            'data' => $this->statusPayload($user),
        ]);
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $type = BillingPlans::SUBSCRIPTION_TYPE;

        if ($user->subscribed($type)) {
            return response()->json([
                'message' => 'already_subscribed',
                'errors' => [
                    'plan' => ['You already have an active subscription. Use the billing portal or swap endpoint to change plans.'],
                ],
            ], 409);
        }

        if (! $this->plans->isConfigured()) {
            throw ValidationException::withMessages([
                'plan' => ['Billing plans are not configured.'],
            ]);
        }

        $priceId = $this->plans->resolvePriceId($request->string('plan')->toString());
        $builder = $user->newSubscription($type, $priceId);

        $trialDays = (int) config('imby.billing.trial_days', 0);
        if ($trialDays > 0) {
            $builder->trialDays($trialDays);
        }

        if ($request->boolean('allow_promotion_codes', (bool) config('imby.billing.allow_promotion_codes', true))) {
            $builder->allowPromotionCodes();
        }

        $successUrl = $this->withCheckoutSessionPlaceholder($request->string('success_url')->toString());

        $checkout = $builder->checkout([
            'success_url' => $successUrl,
            'cancel_url' => $request->string('cancel_url')->toString(),
            'billing_address_collection' => config('imby.billing.collect_address') ? 'required' : 'auto',
            'customer_update' => [
                'address' => 'auto',
                'name' => 'auto',
            ],
        ]);

        $session = $checkout->asStripeCheckoutSession();

        $this->activityLogger->log($user, UserActivityLogger::BILLING_CHECKOUT_STARTED, [
            'plan' => $this->plans->keyForPriceId($priceId),
            'stripe_price' => $priceId,
            'session_id' => $session->id,
        ]);

        return response()->json([
            'message' => 'checkout_session_created',
            'data' => [
                'url' => $session->url,
                'session_id' => $session->id,
            ],
        ]);
    }

    public function portal(PortalRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasStripeId()) {
            return response()->json([
                'message' => 'no_stripe_customer',
                'errors' => [
                    'return_url' => ['Create a subscription via checkout before opening the billing portal.'],
                ],
            ], 400);
        }

        $url = $user->billingPortalUrl($request->string('return_url')->toString());

        $this->activityLogger->log($user, UserActivityLogger::BILLING_PORTAL_OPENED);

        return response()->json([
            'message' => 'billing_portal_session_created',
            'data' => [
                'url' => $url,
            ],
        ]);
    }

    public function swap(SwapPlanRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $type = BillingPlans::SUBSCRIPTION_TYPE;

        if (! $user->subscribed($type)) {
            return response()->json([
                'message' => 'not_subscribed',
                'errors' => [
                    'plan' => ['You must have an active subscription to change plans.'],
                ],
            ], 400);
        }

        $priceId = $this->plans->resolvePriceId($request->string('plan')->toString());
        $subscription = $user->subscription($type);
        $previousPrice = $subscription?->stripe_price;

        if ($previousPrice === $priceId) {
            return response()->json([
                'message' => 'plan_unchanged',
                'data' => $this->statusPayload($user),
            ]);
        }

        try {
            $subscription->swapAndInvoice($priceId);
        } catch (IncompletePayment $e) {
            return response()->json([
                'message' => 'payment_incomplete',
                'errors' => [
                    'plan' => ['Payment requires additional action before the plan can be changed.'],
                ],
                'data' => [
                    'payment_id' => $e->payment->id,
                    'client_secret' => $e->payment->clientSecret(),
                ],
            ], 402);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'failed_to_swap_plan',
                'errors' => [
                    'plan' => ['Unable to change subscription plan.'],
                ],
            ], 500);
        }

        $this->activityLogger->log($user, UserActivityLogger::BILLING_PLAN_CHANGED, [
            'from' => $previousPrice,
            'to' => $priceId,
            'plan' => $this->plans->keyForPriceId($priceId),
        ]);

        return response()->json([
            'message' => 'plan_swapped',
            'data' => $this->statusPayload($user->fresh()),
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $type = BillingPlans::SUBSCRIPTION_TYPE;

        if (! $user->subscribed($type)) {
            return response()->json([
                'message' => 'not_subscribed',
                'errors' => [
                    'subscription' => ['You do not have an active subscription.'],
                ],
            ], 400);
        }

        $immediately = $request->boolean('immediately', false);
        $subscription = $user->subscription($type);

        if ($immediately) {
            $subscription->cancelNow();
        } else {
            $subscription->cancel();
        }

        $this->activityLogger->log($user, UserActivityLogger::BILLING_CANCELED, [
            'immediately' => $immediately,
        ]);

        return response()->json([
            'message' => $immediately ? 'subscription_canceled_immediately' : 'subscription_canceled',
            'data' => $this->statusPayload($user->fresh()),
        ]);
    }

    public function resume(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $type = BillingPlans::SUBSCRIPTION_TYPE;
        $subscription = $user->subscription($type);

        if ($subscription === null || ! $subscription->onGracePeriod()) {
            return response()->json([
                'message' => 'not_on_grace_period',
                'errors' => [
                    'subscription' => ['There is no canceled subscription in its grace period to resume.'],
                ],
            ], 400);
        }

        $subscription->resume();

        $this->activityLogger->log($user, UserActivityLogger::BILLING_RESUMED);

        return response()->json([
            'message' => 'subscription_resumed',
            'data' => $this->statusPayload($user->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(User $user): array
    {
        $type = BillingPlans::SUBSCRIPTION_TYPE;
        $subscription = $user->subscription($type);
        $priceId = $subscription?->stripe_price;

        return [
            'subscribed' => $user->subscribed($type),
            'on_trial' => $user->onTrial($type),
            'on_grace_period' => $subscription?->onGracePeriod() ?? false,
            'canceled' => $subscription?->canceled() ?? false,
            'ended' => $subscription?->ended() ?? false,
            'plan' => $this->plans->keyForPriceId($priceId),
            'stripe_price' => $priceId,
            // Legacy alias used by older SPA clients.
            'stripe_plan' => $priceId,
            'trial_ends_at' => $subscription?->trial_ends_at,
            'ends_at' => $subscription?->ends_at,
            'has_stripe_customer' => $user->hasStripeId(),
        ];
    }

    private function withCheckoutSessionPlaceholder(string $url): string
    {
        if (str_contains($url, '{CHECKOUT_SESSION_ID}')) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'session_id={CHECKOUT_SESSION_ID}';
    }
}
