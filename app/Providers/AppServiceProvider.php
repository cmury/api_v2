<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Scramble docs UI + OpenAPI JSON (local/testing by default).
        Gate::define('viewApiDocs', function ($user = null) {
            return app()->environment(['local', 'testing']);
        });

        RateLimiter::for('geocode', function (Request $request) {
            $perMinute = max(1, (int) config('imby.geocode_rate_per_minute', 60));

            return Limit::perMinute($perMinute)->by($request->ip() ?? 'geocode');
        });
    }
}
