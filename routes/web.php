<?php

use App\Http\Controllers\AchievementStatusController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CartController;
use App\Http\Controllers\Web\CheckoutController;
use App\Http\Controllers\Web\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/shop', [ShopController::class, 'index'])->name('shop');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{product}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{product}', [CartController::class, 'destroy'])->name('cart.items.destroy');

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/web/login', [AuthController::class, 'login'])->name('web.login');

    Route::view('/signup', 'auth.signup')->name('signup');
    Route::post('/web/signup', [AuthController::class, 'register'])->name('web.signup');
});

Route::post('/web/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('web.logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/confirmation/{order}', [CheckoutController::class, 'confirmation'])
        ->name('checkout.confirmation');
});

Route::middleware('auth:sanctum')
    ->get('/users/{user}/achievements', [AchievementStatusController::class, 'show']);
