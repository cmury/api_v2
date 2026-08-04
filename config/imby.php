<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User settings
    |--------------------------------------------------------------------------
    */

    'email_frequencies' => [
        'immediately',
        'daily',
        'weekly',
        'fortnightly',
        'monthly',
        'never',
    ],

    // UI base-layer names plus legacy Google-style values.
    'map_types' => [
        'Light',
        'Street',
        'Dark',
        'Satellite',
        'ROADMAP',
        'SATELLITE',
        'HYBRID',
        'TERRAIN',
    ],

    'default_email_frequency' => 'weekly',

    /*
    |--------------------------------------------------------------------------
    | Map marker limit
    |--------------------------------------------------------------------------
    |
    | Maximum locations returned by GET /api/map/markers (and related GeoJSON
    | endpoints). Matches the old API's app.marker-limit behaviour.
    |
    */

    'marker_limit' => (int) env('IMBY_MARKER_LIMIT', 500),

    /*
    |--------------------------------------------------------------------------
    | Default submitted date window (days)
    |--------------------------------------------------------------------------
    |
    | When a map/search request omits an explicit date range, limit results to
    | applications submitted within this many days (old API default: 365).
    |
    */

    'default_submitted_days' => (int) env('IMBY_DEFAULT_SUBMITTED_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | List pagination defaults
    |--------------------------------------------------------------------------
    */

    'list_per_page' => (int) env('IMBY_LIST_PER_PAGE', 25),
    'list_max_per_page' => (int) env('IMBY_LIST_MAX_PER_PAGE', 100),

    /*
    |--------------------------------------------------------------------------
    | CSV export row limit
    |--------------------------------------------------------------------------
    */

    'csv_limit' => (int) env('IMBY_CSV_LIMIT', 5000),

    /*
    |--------------------------------------------------------------------------
    | Insights (tool-calling warehouse chat via laravel/ai)
    |--------------------------------------------------------------------------
    |
    | Off by default. Enable with INSIGHTS_ENABLED=true and configure a cloud
    | provider (OpenAI by default — same as config/ai.php).
    |
    | The agent selects warehouse tools (authorities, applications, facilities,
    | planning controls, stats, forecasts, taxonomies) grounded in docs/openapi.json.
    | Guarded SQL (run_warehouse_sql) is a last resort when the REST surface cannot
    | express the question.
    |
    */

    'insights_enabled' => (bool) env('INSIGHTS_ENABLED', false),

    'insights_provider' => env('INSIGHTS_PROVIDER', env('AI_DEFAULT_PROVIDER', 'openai')),

    'insights_model' => env('INSIGHTS_MODEL', env('OPENAI_MODEL', 'gpt-4.1-mini')),

    'insights_timeout' => (int) env('INSIGHTS_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Geocoding (Nominatim / OpenStreetMap)
    |--------------------------------------------------------------------------
    |
    | Forward + reverse geocode for map search and Explore place labels.
    | Always call Nominatim from the API (never the browser). Cache aggressively.
    |
    */

    'geocode_base_url' => env('GEOCODE_BASE_URL', 'https://nominatim.openstreetmap.org'),

    'geocode_user_agent' => env(
        'GEOCODE_USER_AGENT',
        'IMBY/2.0 (https://imby.com.au; geocode@imby.com.au)',
    ),

    'geocode_countrycodes' => env('GEOCODE_COUNTRYCODES', 'au'),

    /** Cache TTL in seconds (default 7 days). */
    'geocode_cache_ttl' => (int) env('GEOCODE_CACHE_TTL', 604800),

    'geocode_timeout' => (int) env('GEOCODE_TIMEOUT', 8),

    /** Max requests per minute per IP for /geocode*. */
    'geocode_rate_per_minute' => (int) env('GEOCODE_RATE_PER_MINUTE', 60),

];
