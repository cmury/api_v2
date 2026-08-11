<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Relying Party ID
    |--------------------------------------------------------------------------
    |
    | Hostname only (no scheme/port). For local SPA + API on different ports,
    | use "localhost". Production should be the shared registrable domain
    | (e.g. imby.app).
    |
    */

    'relying_party_id' => env(
        'PASSKEYS_RELYING_PARTY_ID',
        parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'
    ),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Full origins permitted to complete WebAuthn ceremonies (scheme + host
    | + port). Include every frontend origin that will register or assert
    | passkeys. Comma-separated via PASSKEYS_ALLOWED_ORIGINS.
    |
    */

    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', (string) env(
            'PASSKEYS_ALLOWED_ORIGINS',
            implode(',', array_filter([
                env('APP_URL'),
                env('FRONTEND_URL'),
                'http://localhost:5174',
                'http://127.0.0.1:5174',
            ]))
        ))
    ))),

    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),

    'timeout' => (int) env('PASSKEYS_TIMEOUT', 60000),

    // Package defaults (unused — api_v2 registers its own Sanctum JSON routes).
    'guard' => 'web',
    'middleware' => ['web'],
    'management_middleware' => [],
    'throttle' => 'throttle:6,1',
    'redirect' => '/',

];
