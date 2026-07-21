<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\InsightsController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserSearchController;
use Illuminate\Support\Facades\Route;

// Status route for health check
Route::get('/status', StatusController::class);

// AI insights: natural-language questions over the data warehouse (read-only),
// with per-user chat history stored as threads/messages.
Route::middleware('auth:sanctum')->prefix('insights')->group(function () {
    Route::post('/ask', [InsightsController::class, 'ask']);
    Route::get('/threads', [InsightsController::class, 'threads']);
    Route::get('/threads/{thread}', [InsightsController::class, 'thread']);
    Route::delete('/threads/{thread}', [InsightsController::class, 'destroyThread']);
});

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

    // User profile and settings routes
    Route::get('/profile', [UserController::class, 'show']);
    Route::put('/profile', [UserController::class, 'update']);
    Route::get('/settings', [UserController::class, 'showSettings']);
    Route::put('/settings', [UserController::class, 'updateSettings']);
    // User activity log route
    Route::get('/log', [UserController::class, 'log']);
    // User search routes
    Route::get('/searches', [UserSearchController::class, 'index']);
    Route::post('/searches', [UserSearchController::class, 'store']);
    Route::get('/searches/{search}', [UserSearchController::class, 'show']);
    Route::put('/searches/{search}', [UserSearchController::class, 'update']);
    Route::delete('/searches/{search}', [UserSearchController::class, 'destroy']);
    // User account deletion route
    Route::delete('/', [UserController::class, 'destroy']);

});
