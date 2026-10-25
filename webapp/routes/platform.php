<?php

use App\Http\Controllers\Platform\AuthController;
use App\Http\Controllers\Platform\CompanyController;
use App\Http\Controllers\Platform\HomeController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SystemController;
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

    // M13: companies and their plans, plans and coupons, storage and the database export.
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::post('/companies/{company}/subscribe', [CompanyController::class, 'subscribe'])->name('companies.subscribe');
    Route::post('/companies/{company}/trial', [CompanyController::class, 'extendTrial'])->name('companies.trial');
    Route::post('/companies/{company}/suspend', [CompanyController::class, 'suspend'])->name('companies.suspend');
    Route::post('/companies/{company}/resume', [CompanyController::class, 'resume'])->name('companies.resume');
    Route::get('/plans', [PlanController::class, 'index'])->name('plans');
    Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
    Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::post('/coupons', [PlanController::class, 'storeCoupon'])->name('coupons.store');
    Route::post('/coupons/{coupon}/toggle', [PlanController::class, 'toggleCoupon'])->name('coupons.toggle');
    Route::get('/system', [SystemController::class, 'index'])->name('system');
    Route::post('/system/prune', [SystemController::class, 'prune'])->name('system.prune');
    Route::post('/system/export', [SystemController::class, 'export'])->middleware('throttle:6,60')->name('system.export');
    Route::put('/system/announcement', [SystemController::class, 'announce'])->name('system.announce');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
