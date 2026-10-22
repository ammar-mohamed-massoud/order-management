<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;

use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');
});

Route::middleware('jwt.auth')->group(function (): void {
    // TODO: Here will go the routes for all payment and order management
    Route::apiResource('orders', OrderController::class);
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
});

Route::fallback(function () {
    return response()->json(['message' => 'Resource not found'], 404);
});
