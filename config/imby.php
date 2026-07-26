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
    | Insights (experimental NL→SQL chat)
    |--------------------------------------------------------------------------
    |
    | Off by default. Enable for local AI testing; start Ollama with:
    |   docker compose --profile insights up -d
    |
    */

    'insights_enabled' => (bool) env('INSIGHTS_ENABLED', false),

];
