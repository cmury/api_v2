<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User settings
    |--------------------------------------------------------------------------
    */

    'email_frequencies' => [
        'daily',
        'weekly',
        'fortnightly',
        'monthly',
    ],

    'map_types' => [
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

];
