<?php

use App\Http\Controllers\AchievementStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth:sanctum')
    ->get('/users/{user}/achievements', [AchievementStatusController::class, 'show']);
