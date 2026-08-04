<?php

namespace App\Providers;

use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Schema lives in agents_v2 (users_subscriptions / users_subscription_items).
        Cashier::useCustomerModel(User::class);
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        // Scramble docs UI + OpenAPI JSON (local/testing by default).
        Gate::define('viewApiDocs', function ($user = null) {
            return app()->environment(['local', 'testing']);
        });

        RateLimiter::for('geocode', function (Request $request) {
            $perMinute = max(1, (int) config('imby.geocode_rate_per_minute', 60));

            return Limit::perMinute($perMinute)->by($request->ip() ?? 'geocode');
        });

        RateLimiter::for('reports', function (Request $request) {
            $perMinute = max(1, (int) config('imby.reports.rate_per_minute', 20));

            return Limit::perMinute($perMinute)->by($request->ip() ?? 'reports');
        });
    }
}
