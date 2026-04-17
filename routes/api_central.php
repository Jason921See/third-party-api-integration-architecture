<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Central\AuthController;

Route::prefix('central')->group(function () {

    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
