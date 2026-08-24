<?php

use App\Http\Controllers\AchievementStatusController;
use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/web/login', [AuthController::class, 'login'])->name('web.login');

    Route::view('/signup', 'auth.signup')->name('signup');
    Route::post('/web/signup', [AuthController::class, 'register'])->name('web.signup');
});

Route::post('/web/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('web.logout');

Route::middleware('auth:sanctum')
    ->get('/users/{user}/achievements', [AchievementStatusController::class, 'show']);
