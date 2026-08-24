<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CashbackController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentAccountController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/signup', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/cashback/webhook', PaystackWebhookController::class)
    ->middleware(['paystack.ip', 'paystack.signature']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::post('/products', [ProductController::class, 'store']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    Route::get('/cashbacks', [CashbackController::class, 'index']);
    Route::get('/cashbacks/{cashback}', [CashbackController::class, 'show']);

    Route::get('/payment-accounts', [PaymentAccountController::class, 'index']);
    Route::put('/payment-accounts/{provider}', [PaymentAccountController::class, 'upsert']);
    Route::delete('/payment-accounts/{provider}', [PaymentAccountController::class, 'destroy']);
});
