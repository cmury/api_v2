<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Simple browser page to try the AI insights chat against the API.
Route::view('/insights', 'insights');
