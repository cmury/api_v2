<?php

namespace App\Http\Middleware;

use App\Support\Billing\PlanEntitlements;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if ($user === null) {
            throw new AuthenticationException;
        }

        $plan = $user->billingPlanKey();
        if (! PlanEntitlements::allows($plan, $feature)) {
            return response()->json([
                'message' => 'plan_required',
                'feature' => $feature,
                'required_plan' => PlanEntitlements::minimumPlan($feature),
                'plan' => $plan,
            ], 403);
        }

        return $next($request);
    }
}
