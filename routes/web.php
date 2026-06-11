<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->where('provider', 'google|github');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->where('provider', 'google|github');

Route::prefix('api')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Password reset — throttled separately
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/password/forgot', [PasswordResetController::class, 'forgot']);
        Route::post('/password/reset', [PasswordResetController::class, 'reset']);
        Route::get('/password/verify-token', [PasswordResetController::class, 'verifyToken']);
    });

    // Check availability — public, no auth needed
    Route::get('/check-availability', [UserController::class, 'checkAvailability']);
});
