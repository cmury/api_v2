<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
    }
}
