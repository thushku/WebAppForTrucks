<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SignupController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| The web app for office users. The public marketing website arrives with
| milestone M14; until then "/" goes to sign-in or the dashboard.
*/

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'))->name('home');

// The picture challenge (sign-in, sign-up and the console all use it).
Route::get('/captcha', [LoginController::class, 'captcha'])->name('captcha');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'attempt'])->middleware('throttle:login')->name('login.attempt');
    Route::get('/login/code', [LoginController::class, 'showOtp'])->name('login.otp');
    Route::post('/login/code', [LoginController::class, 'verifyOtp'])->middleware('throttle:otp')->name('login.otp.verify');
    Route::post('/login/code/resend', [LoginController::class, 'resendOtp'])->middleware('throttle:otp')->name('login.otp.resend');

    Route::get('/signup', [SignupController::class, 'show'])->name('signup');
    Route::post('/signup', [SignupController::class, 'store'])->middleware('throttle:login')->name('signup.store');
    Route::get('/signup/sent', [SignupController::class, 'sent'])->name('signup.sent');
    Route::post('/signup/resend', [SignupController::class, 'resend'])->middleware('throttle:login')->name('signup.resend');

    Route::get('/password/forgot', [PasswordController::class, 'forgot'])->name('password.forgot');
    Route::post('/password/forgot', [PasswordController::class, 'send'])->middleware('throttle:login')->name('password.email');
    Route::get('/password/reset/{token}', [PasswordController::class, 'edit'])->name('password.reset');
    Route::post('/password/reset/{token}', [PasswordController::class, 'update'])->middleware('throttle:login')->name('password.update');
});

// Verification links work signed in or not.
Route::get('/verify/{token}', [SignupController::class, 'verify'])->name('signup.verify');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/companies', [LoginController::class, 'showChoice'])->name('company.choose');
    Route::post('/companies', [LoginController::class, 'choose'])->name('company.switch');

    Route::middleware('company')->group(function () {
        Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    });
});
