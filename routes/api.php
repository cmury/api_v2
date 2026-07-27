<?php

use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\AuthorityController;
use App\Http\Controllers\Api\InsightsController;
use App\Http\Controllers\Api\LegislationController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserSearchController;
use Illuminate\Support\Facades\Route;

// Status route for health check
Route::get('/status', StatusController::class);

// Public map + coverage
Route::get('/map/markers', [MapController::class, 'markers']);
Route::get('/authorities/coverage', [AuthorityController::class, 'coverage']);

// Experimental Insights (NL→SQL). Gated by INSIGHTS_ENABLED.
if (config('imby.insights_enabled')) {
    Route::middleware('auth:sanctum')->prefix('insights')->group(function () {
        Route::post('/ask', [InsightsController::class, 'ask']);
        Route::get('/threads', [InsightsController::class, 'threads']);
        Route::get('/threads/{thread}', [InsightsController::class, 'thread']);
        Route::delete('/threads/{thread}', [InsightsController::class, 'destroyThread']);
    });
}

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/password/forgot', [PasswordResetController::class, 'forgot']);
    Route::post('/password/reset', [PasswordResetController::class, 'reset']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/password/change', [AuthController::class, 'changePassword']);
    });
});

// User routes
Route::middleware('auth:sanctum')->prefix('user')->group(function () {
    Route::get('/profile', [UserController::class, 'show']);
    Route::put('/profile', [UserController::class, 'update']);
    Route::get('/settings', [UserController::class, 'showSettings']);
    Route::put('/settings', [UserController::class, 'updateSettings']);
    Route::get('/log', [UserController::class, 'log']);
    Route::get('/searches', [UserSearchController::class, 'index']);
    Route::post('/searches', [UserSearchController::class, 'store']);
    Route::get('/searches/{search}', [UserSearchController::class, 'show']);
    Route::put('/searches/{search}', [UserSearchController::class, 'update']);
    Route::delete('/searches/{search}', [UserSearchController::class, 'destroy']);
    Route::delete('/', [UserController::class, 'destroy']);
});

// Warehouse read APIs (ported from old api, collapsed where duplicated)
Route::middleware('auth:sanctum')->group(function () {

    // Mapping endpoints for map markers and CSV export
    Route::get('/map/markers/csv', [MapController::class, 'csv']);

    Route::get('/notifications', [NotificationController::class, 'searches']);

    // Authority endpoints
    Route::get('/authorities', [AuthorityController::class, 'index']);
    Route::get('/authorities/statistics', [AuthorityController::class, 'statisticsIndex']);
    Route::get('/authorities/{authority}/statistics', [AuthorityController::class, 'statistics']);
    Route::get('/authorities/{authority}/locations', [AuthorityController::class, 'locations']);
    Route::get('/authorities/{authority}/boundary', [AuthorityController::class, 'boundary']);
    Route::get('/authorities/{authority}', [AuthorityController::class, 'show']);

    // Application endpoints
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::get('/applications/{application}/legislation', [ApplicationController::class, 'legislations']);
    Route::get('/applications/{application}', [ApplicationController::class, 'show']);

    // Legislation endpoints
    Route::get('/legislation', [LegislationController::class, 'index']);
    Route::get('/legislation/{legislation}/applications', [LegislationController::class, 'applications']);
    Route::get('/legislation/{legislation}', [LegislationController::class, 'show']);

    // Location endpoints
    Route::get('/locations', [LocationController::class, 'index']);
    Route::get('/locations/{location}', [LocationController::class, 'show']);
    Route::get('/locations/{location}/applications', [LocationController::class, 'applications']);

    // Stats and charts endpoints
    Route::get('/stats', [StatsController::class, 'show']);
    Route::get('/charts', [StatsController::class, 'chart']);

    // Taxonomy endpoints
    Route::get('/taxonomies/application-classes', [TaxonomyController::class, 'applicationClasses']);
    Route::get('/taxonomies/application-types', [TaxonomyController::class, 'applicationTypes']);
    Route::get('/taxonomies/development-classes', [TaxonomyController::class, 'developmentClasses']);
    Route::get('/taxonomies/development-types', [TaxonomyController::class, 'developmentTypes']);
    Route::get('/taxonomies/decision-classes', [TaxonomyController::class, 'decisionClasses']);
    Route::get('/taxonomies/decision-types', [TaxonomyController::class, 'decisionTypes']);
});
