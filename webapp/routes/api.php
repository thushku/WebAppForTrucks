<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CrewController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Owner\MoneyController;
use App\Http\Controllers\Api\Owner\OfficeController;
use App\Http\Controllers\Api\Owner\WriteController;
use App\Http\Controllers\Api\WebLoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| The phone-app API (blueprint 01 §4). Version 1 lives under /api/v1.
|
|   auth/*      sign a phone in: activation code (crew) or SMS code (owner)
|   companies   the companies this login works for; then X-Company on every call
|   crew/*      the crew app: sync, entries, photos, GPS, commands, papers
|   approvals/* the owner app: entries waiting for the office
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
                    'company' => ['id' => $member->company->uuid, 'name' => $member->company->name, 'country' => $member->company->country, 'currency' => $member->company->currency,
                        'status' => $member->company->status, 'read_only' => $member->company->isReadOnly(), 'trial_ends_at' => $member->company->trial_ends_at?->toDateString()],
                    'announcement' => app(\App\Platform\Settings\PlatformSettings::class)->get('announcement'),
                    'permissions' => app(\App\Platform\Authorization\PermissionResolver::class)->permissionsFor($member),
                    'unread_notifications' => \App\Platform\Notifications\UserNotification::for($request->user())->whereNull('read_at')->count(),
                    'server_time' => now()->toIso8601String(),
                ]);
            })->name('api.me');

            // The inbox behind the push (M20), for both apps.
            Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications');
            Route::post('/notifications/read', [NotificationController::class, 'read'])->name('api.notifications.read');
            Route::get('/notifications/settings', [NotificationController::class, 'settings'])->name('api.notifications.settings');
            Route::put('/notifications/settings', [NotificationController::class, 'saveSettings'])->name('api.notifications.settings.update');

            // The owner app approves a web sign-in it has scanned (decision P7).
            Route::post('/web-login/inspect', [WebLoginController::class, 'inspect'])->name('api.web-login.inspect');
            Route::post('/web-login/approve', [WebLoginController::class, 'approve'])->name('api.web-login.approve');

            // The owner app: the crew's entries waiting for the office (M7).
            Route::prefix('approvals')->name('api.approvals.')->middleware('permission:event.verify')->group(function () {
                Route::get('/', [ApprovalController::class, 'index'])->name('index');
                Route::post('/{uuid}', [ApprovalController::class, 'decide'])->whereUuid('uuid')->name('decide');
                Route::get('/{uuid}/photos/{photo}', [ApprovalController::class, 'photo'])->whereUuid('uuid')->name('photo');
            });

            // The owner app (M15): the same screens and permissions as the web, read only but for sharing a bill.
            Route::prefix('owner')->name('api.owner.')->group(function () {
                Route::get('/dashboard', [OfficeController::class, 'dashboard'])->middleware('permission:dashboard.view')->name('dashboard');
                Route::get('/map', [OfficeController::class, 'map'])->middleware('permission:map.view')->name('map');
                Route::get('/trips', [OfficeController::class, 'trips'])->middleware('permission:trip.view')->name('trips');
                Route::get('/trips/{uuid}', [OfficeController::class, 'trip'])->whereUuid('uuid')->middleware('permission:trip.view')->name('trips.show');
                Route::get('/trips/{uuid}/route', [OfficeController::class, 'route'])->whereUuid('uuid')->middleware('permission:map.view')->name('trips.route');
                Route::get('/trucks', [OfficeController::class, 'trucks'])->middleware('permission:truck.view')->name('trucks');
                Route::get('/papers', [OfficeController::class, 'papers'])->middleware('permission:document.view')->name('papers');
                Route::get('/papers/{document}/files/{file}', [OfficeController::class, 'paperFile'])->middleware('permission:document.view')->name('papers.file');
                Route::get('/invoices', [MoneyController::class, 'invoices'])->middleware('permission:invoice.view')->name('invoices');
                Route::get('/invoices/{invoice}', [MoneyController::class, 'invoice'])->whereNumber('invoice')->middleware('permission:invoice.view')->name('invoices.show');
                Route::post('/invoices/{invoice}/shared', [MoneyController::class, 'shared'])->whereNumber('invoice')->middleware('permission:invoice.send')->name('invoices.shared');
                Route::get('/ledgers', [MoneyController::class, 'ledgers'])->middleware('permission:ledger.view')->name('ledgers');
                Route::get('/ledgers/{ledger}', [MoneyController::class, 'statement'])->whereNumber('ledger')->middleware('permission:ledger.view')->name('ledgers.show');

                // Making things from the phone (M23, api-v1 §12): each write runs once per Idempotency-Key.
                Route::get('/masters', [WriteController::class, 'masters'])->middleware('permission:trip.create,invoice.create,payment.record,document.manage,trip.settle')->name('masters');
                Route::post('/trips', [WriteController::class, 'storeTrip'])->middleware('permission:trip.create')->name('trips.store');
                Route::post('/trips/{uuid}/send', [WriteController::class, 'sendTrip'])->whereUuid('uuid')->middleware('permission:trip.assign')->name('trips.send');
                Route::get('/unbilled', [WriteController::class, 'unbilled'])->middleware('permission:invoice.create')->name('unbilled');
                Route::post('/invoices', [WriteController::class, 'storeInvoice'])->middleware('permission:invoice.create')->name('invoices.store');
                Route::post('/invoices/{invoice}/finalise', [WriteController::class, 'finaliseInvoice'])->whereNumber('invoice')->middleware('permission:invoice.finalise')->name('invoices.finalise');
                Route::post('/receipts', [WriteController::class, 'storeReceipt'])->middleware('permission:payment.record')->name('receipts.store');
                Route::post('/papers', [WriteController::class, 'storePaper'])->middleware('permission:document.manage')->name('papers.store');
                // Quick cash for the crew (M27): the khata in short, then a cash entry.
                Route::get('/crew/{person}/khata', [WriteController::class, 'crewKhata'])->whereNumber('person')->middleware('permission:trip.settle')->name('crew.khata');
                Route::post('/crew/{person}/cash', [WriteController::class, 'storeCrewCash'])->whereNumber('person')->middleware('permission:trip.settle')->name('crew.cash');
            });

            Route::prefix('crew')->name('api.crew.')->group(function () {
                Route::get('/sync', [CrewController::class, 'sync'])->name('sync');
                Route::post('/events', [CrewController::class, 'events'])->name('events');
                Route::patch('/events/{uuid}', [CrewController::class, 'edit'])->whereUuid('uuid')->name('events.edit');
                Route::post('/events/{uuid}/cancel', [CrewController::class, 'cancel'])->whereUuid('uuid')->name('events.cancel');
                Route::post('/events/{uuid}/photos', [CrewController::class, 'photo'])->whereUuid('uuid')->name('events.photo');
                Route::post('/gps', [CrewController::class, 'gps'])->name('gps');
                Route::post('/commands', [CrewController::class, 'commands'])->name('commands');
                Route::get('/papers/{document}/files/{file}', [CrewController::class, 'paperFile'])->name('papers.file');
                Route::get('/money', [CrewController::class, 'money'])->name('money');
            });
        });
    });
});
