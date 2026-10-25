<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CrewController;
use App\Http\Controllers\Api\WebLoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| The phone-app API (blueprint 01 §4). Version 1 lives under /api/v1.
|
|   auth/*      sign a phone in: activation code (crew) or SMS code (owner)
|   companies   the companies this login works for; then X-Company on every call
|   crew/*      the crew app: sync, entries, photos, GPS, commands, papers
*/

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    // Lets the phone check it can reach the server and read the server time
    // (for its clock check) before anyone has signed in.
    Route::get('/ping', fn () => response()->json([
        'ok'          => true,
        'server_time' => now()->toIso8601String(),
        'version'     => config('app.version'),
    ]))->name('api.ping');

    Route::middleware('throttle:otp')->group(function () {
        Route::post('/auth/activate', [AuthController::class, 'activate'])->name('api.auth.activate');
        Route::post('/auth/otp', [AuthController::class, 'requestOtp'])->name('api.auth.otp');
        Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->name('api.auth.otp.verify');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/companies', [AuthController::class, 'companies'])->name('api.companies');
        Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('api.auth.refresh');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::post('/auth/pin', [AuthController::class, 'setPin'])->name('api.auth.pin');
        Route::post('/device', [AuthController::class, 'device'])->name('api.device');

        Route::middleware('api.company')->group(function () {
            Route::get('/me', function (Request $request) {
                $member = app(\App\Platform\Tenancy\CompanyContext::class)->member();

                return response()->json([
                    'account' => ['name' => $request->user()->name, 'mobile' => $request->user()->mobile, 'locale' => $request->user()->locale],
                    'company' => ['id' => $member->company->uuid, 'name' => $member->company->name, 'country' => $member->company->country, 'currency' => $member->company->currency],
                    'permissions' => app(\App\Platform\Authorization\PermissionResolver::class)->permissionsFor($member),
                    'server_time' => now()->toIso8601String(),
                ]);
            })->name('api.me');

            // The owner app approves a web sign-in it has scanned (decision P7).
            Route::post('/web-login/inspect', [WebLoginController::class, 'inspect'])->name('api.web-login.inspect');
            Route::post('/web-login/approve', [WebLoginController::class, 'approve'])->name('api.web-login.approve');

            Route::prefix('crew')->name('api.crew.')->group(function () {
                Route::get('/sync', [CrewController::class, 'sync'])->name('sync');
                Route::post('/events', [CrewController::class, 'events'])->name('events');
                Route::patch('/events/{uuid}', [CrewController::class, 'edit'])->whereUuid('uuid')->name('events.edit');
                Route::post('/events/{uuid}/cancel', [CrewController::class, 'cancel'])->whereUuid('uuid')->name('events.cancel');
                Route::post('/events/{uuid}/photos', [CrewController::class, 'photo'])->whereUuid('uuid')->name('events.photo');
                Route::post('/gps', [CrewController::class, 'gps'])->name('gps');
                Route::post('/commands', [CrewController::class, 'commands'])->name('commands');
                Route::get('/papers/{document}/files/{file}', [CrewController::class, 'paperFile'])->name('papers.file');
            });
        });
    });
});
