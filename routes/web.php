<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/api/status');

// Experimental Insights chat UI (requires INSIGHTS_ENABLED=true).
if (config('imby.insights_enabled')) {
    Route::view('/insights', 'insights');
}
