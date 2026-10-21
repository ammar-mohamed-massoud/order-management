<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');
});

Route::middleware('jwt.auth')->group(function (): void {
    // TODO: Here will go the routes for all payment and order management
});

Route::fallback(function () {
    return response()->json(['message' => 'Resource not found'], 404);
});
