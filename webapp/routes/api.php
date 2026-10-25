<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| The phone-app API (blueprint 01 §4). Version 1 lives under /api/v1.
| Sign-in, sync, photos, GPS and commands arrive with milestone M6.
*/

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    // Lets the phone check it can reach the server and read the server time
    // (for its clock check) before anyone has signed in.
    Route::get('/ping', fn () => response()->json([
        'ok'          => true,
        'server_time' => now()->toIso8601String(),
        'version'     => config('app.version'),
    ]))->name('api.ping');

    Route::middleware(['auth:sanctum', 'api.company'])->group(function () {
        Route::get('/me', function (Request $request) {
            $member = app(\App\Platform\Tenancy\CompanyContext::class)->member();

            return response()->json([
                'account' => ['name' => $request->user()->name, 'mobile' => $request->user()->mobile, 'locale' => $request->user()->locale],
                'company' => ['id' => $member->company->uuid, 'name' => $member->company->name, 'country' => $member->company->country, 'currency' => $member->company->currency],
                'permissions' => app(\App\Platform\Authorization\PermissionResolver::class)->permissionsFor($member),
                'server_time' => now()->toIso8601String(),
            ]);
        })->name('api.me');
    });
});
