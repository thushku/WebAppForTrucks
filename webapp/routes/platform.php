<?php

use App\Http\Controllers\Platform\AuthController;
use App\Http\Controllers\Platform\HomeController;
use Illuminate\Support\Facades\Route;

/*
| The super-admin console, under the secret prefix from config/platform.php.
| Route names start with "platform.".
*/

Route::middleware('guest:platform')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'attempt'])->middleware('throttle:login')->name('login.attempt');
    Route::get('/login/code', [AuthController::class, 'showOtp'])->name('otp');
    Route::post('/login/code', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp')->name('otp.verify');
});

Route::middleware('auth:platform')->group(function () {
    Route::get('/', [HomeController::class, 'home'])->name('home');
    Route::post('/accounts/{account}/unlock', [HomeController::class, 'unlock'])->name('accounts.unlock');
    Route::get('/settings', [HomeController::class, 'settings'])->name('settings');
    Route::put('/settings', [HomeController::class, 'saveSettings'])->name('settings.save');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
