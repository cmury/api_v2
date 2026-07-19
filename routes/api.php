<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\StatusController;
use Illuminate\Support\Facades\Route;

Route::get('/status', StatusController::class);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/password/forgot', [PasswordResetController::class, 'forgot']);
    Route::post('/password/reset', [PasswordResetController::class, 'reset']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/user', [AuthController::class, 'updateProfile']);
        Route::post('/password/change', [AuthController::class, 'changePassword']);
    });
});
