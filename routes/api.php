<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Route::prefix('v1')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/user', [UserController::class, 'show']);

        Route::get('/balance', [PaymentController::class, 'balance']);

        Route::post('/payments', [PaymentController::class, 'store']);

        Route::get('/payments', [PaymentController::class, 'index']);

    });

});

// Broadcast authorization route protected by auth:sanctum under api/v1 prefix
Broadcast::routes(['prefix' => 'v1', 'middleware' => ['auth:sanctum']]);
