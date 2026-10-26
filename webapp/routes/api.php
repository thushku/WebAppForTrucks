<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CrewController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Owner\CompanyController;
use App\Http\Controllers\Api\Owner\DealsController;
use App\Http\Controllers\Api\Owner\HireController;
use App\Http\Controllers\Api\Owner\LeaveController;
use App\Http\Controllers\Api\Owner\MastersController;
use App\Http\Controllers\Api\Owner\MoneyController;
use App\Http\Controllers\Api\Owner\OfficeController;
use App\Http\Controllers\Api\Owner\SettlementController;
use App\Http\Controllers\Api\Owner\TyresController;
use App\Http\Controllers\Api\Owner\WriteController;
use App\Http\Controllers\Api\WebLoginController;
use App\Http\Controllers\ProfileController;
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

        // My profile and Help (M28): the person's own, whichever company the app is in.
        Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile');
        Route::post('/profile', [ProfileController::class, 'save'])->middleware('throttle:20,1')->name('api.profile.update');
        Route::get('/profile/photo', [ProfileController::class, 'photo'])->name('api.profile.photo');
        Route::get('/help', [ProfileController::class, 'helpApi'])->name('api.help');

        Route::middleware('api.company')->group(function () {
            Route::get('/me', function (Request $request) {
                $member = app(\App\Platform\Tenancy\CompanyContext::class)->member();

                return response()->json([
                    'account' => ['name' => $request->user()->name, 'mobile' => $request->user()->mobile, 'locale' => $request->user()->locale,
                        'photo_url' => app(\App\Platform\Auth\Profile::class)->toApi($request->user())['photo_url']],
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
                Route::get('/reports/routes', [OfficeController::class, 'routes'])->middleware('permission:report.view')->name('reports.routes');
                Route::get('/papers/{document}/files/{file}', [OfficeController::class, 'paperFile'])->middleware('permission:document.view')->name('papers.file');
                Route::get('/invoices', [MoneyController::class, 'invoices'])->middleware('permission:invoice.view')->name('invoices');
                Route::get('/invoices/{invoice}', [MoneyController::class, 'invoice'])->whereNumber('invoice')->middleware('permission:invoice.view')->name('invoices.show');
                Route::post('/invoices/{invoice}/shared', [MoneyController::class, 'shared'])->whereNumber('invoice')->middleware('permission:invoice.send')->name('invoices.shared');
                Route::get('/ledgers', [MoneyController::class, 'ledgers'])->middleware('permission:ledger.view')->name('ledgers');
                Route::get('/ledgers/{ledger}', [MoneyController::class, 'statement'])->whereNumber('ledger')->middleware('permission:ledger.view')->name('ledgers.show');

                // Making things from the phone (M23, api-v1 §12): each write runs once per Idempotency-Key.
                Route::get('/masters', [WriteController::class, 'masters'])->middleware('permission:trip.create,invoice.create,payment.record,document.manage,trip.settle,party.manage,truck.manage,people.manage,settings.masters')->name('masters');
                Route::post('/trips', [WriteController::class, 'storeTrip'])->middleware('permission:trip.create')->name('trips.store');
                Route::post('/trips/{uuid}/send', [WriteController::class, 'sendTrip'])->whereUuid('uuid')->middleware('permission:trip.assign')->name('trips.send');
                // Settling a trip (M42): the crew's trip cash, then settle each person and close.
                Route::middleware('permission:trip.settle')->group(function () {
                    Route::get('/trips/{uuid}/settlement', [SettlementController::class, 'show'])->whereUuid('uuid')->name('trips.settlement');
                    Route::post('/trips/{uuid}/settle/{person}', [SettlementController::class, 'settle'])->whereUuid('uuid')->whereNumber('person')->name('trips.settle');
                    Route::post('/trips/{uuid}/settle', [SettlementController::class, 'settleAll'])->whereUuid('uuid')->name('trips.settle.all');
                    Route::post('/trips/{uuid}/close', [SettlementController::class, 'close'])->whereUuid('uuid')->name('trips.close');
                });
                // A hired truck's hire challan (M42): draft, edit, issue, then its money.
                Route::middleware('permission:hire.manage')->group(function () {
                    Route::get('/trips/{uuid}/hire', [HireController::class, 'index'])->whereUuid('uuid')->name('trips.hire');
                    Route::post('/trips/{uuid}/hire', [HireController::class, 'store'])->whereUuid('uuid')->name('trips.hire.store');
                    Route::put('/trips/{uuid}/hire/{challan}', [HireController::class, 'update'])->whereUuid('uuid')->whereNumber('challan')->name('trips.hire.update');
                    Route::post('/trips/{uuid}/hire/{challan}/issue', [HireController::class, 'issue'])->whereUuid('uuid')->whereNumber('challan')->name('trips.hire.issue');
                    Route::post('/trips/{uuid}/hire/{challan}/lines', [HireController::class, 'line'])->whereUuid('uuid')->whereNumber('challan')->name('trips.hire.lines');
                });
                Route::get('/unbilled', [WriteController::class, 'unbilled'])->middleware('permission:invoice.create')->name('unbilled');
                Route::post('/invoices', [WriteController::class, 'storeInvoice'])->middleware('permission:invoice.create')->name('invoices.store');
                Route::post('/invoices/{invoice}/finalise', [WriteController::class, 'finaliseInvoice'])->whereNumber('invoice')->middleware('permission:invoice.finalise')->name('invoices.finalise');
                Route::post('/invoices/{invoice}/notes', [WriteController::class, 'storeNote'])->whereNumber('invoice')->middleware('permission:invoice.create')->name('invoices.notes');
                Route::post('/consignments/{consignment}/track', [WriteController::class, 'trackLoad'])->whereNumber('consignment')->middleware('permission:trip.view')->name('consignments.track');
                Route::get('/clients/{party}/page', [WriteController::class, 'clientPage'])->whereNumber('party')->middleware('permission:invoice.send')->name('clients.page');
                Route::post('/clients/{party}/page', [WriteController::class, 'shareClientPage'])->whereNumber('party')->middleware('permission:invoice.send')->name('clients.page.share');
                Route::delete('/clients/{party}/page', [WriteController::class, 'stopClientPage'])->whereNumber('party')->middleware('permission:invoice.send')->name('clients.page.stop');
                Route::post('/receipts', [WriteController::class, 'storeReceipt'])->middleware('permission:payment.record')->name('receipts.store');
                Route::post('/papers', [WriteController::class, 'storePaper'])->middleware('permission:document.manage')->name('papers.store');
                // The masters (M29, 13-parity #9): list, open, add (once per key), edit (only the fields sent), deactivate.
                Route::get('/clients', [MastersController::class, 'clients'])->middleware('permission:party.view')->name('clients');
                Route::get('/clients/{party}', [MastersController::class, 'client'])->whereNumber('party')->middleware('permission:party.view')->name('clients.show');
                Route::post('/clients', [MastersController::class, 'storeClient'])->middleware('permission:party.manage')->name('clients.store');
                Route::put('/clients/{party}', [MastersController::class, 'updateClient'])->whereNumber('party')->middleware('permission:party.manage')->name('clients.update');
                Route::post('/clients/{party}/active', [MastersController::class, 'activeClient'])->whereNumber('party')->middleware('permission:party.manage')->name('clients.active');
                Route::get('/places', [MastersController::class, 'places'])->middleware('permission:trip.view,trip.create,settings.masters')->name('places');
                Route::post('/places', [MastersController::class, 'storePlace'])->middleware('permission:settings.masters')->name('places.store');
                Route::put('/places/{place}', [MastersController::class, 'updatePlace'])->whereNumber('place')->middleware('permission:settings.masters')->name('places.update');
                Route::post('/places/{place}/active', [MastersController::class, 'activePlace'])->whereNumber('place')->middleware('permission:settings.masters')->name('places.active');
                Route::get('/trucks/{truck}', [MastersController::class, 'truck'])->whereNumber('truck')->middleware('permission:truck.view')->name('trucks.show');
                Route::post('/trucks', [MastersController::class, 'storeTruck'])->middleware('permission:truck.manage')->name('trucks.store');
                Route::put('/trucks/{truck}', [MastersController::class, 'updateTruck'])->whereNumber('truck')->middleware('permission:truck.manage')->name('trucks.update');
                Route::post('/trucks/{truck}/status', [MastersController::class, 'truckStatus'])->whereNumber('truck')->middleware('permission:truck.manage')->name('trucks.status');
                Route::get('/trucks/{truck}/trips', [MastersController::class, 'truckTrips'])->whereNumber('truck')->middleware('permission:trip.view')->name('trucks.trips');
                Route::get('/trucks/{truck}/fuel', [MastersController::class, 'truckFuel'])->whereNumber('truck')->middleware('permission:report.view,event.verify')->name('trucks.fuel');
                Route::get('/trucks/{truck}/costs', [MastersController::class, 'truckCosts'])->whereNumber('truck')->middleware('permission:report.view,accounts.view,ledger.view')->name('trucks.costs');
                Route::get('/crew', [MastersController::class, 'crew'])->middleware('permission:people.view')->name('crew');
                Route::get('/crew/{person}', [MastersController::class, 'person'])->whereNumber('person')->middleware('permission:people.view')->name('crew.show');
                Route::post('/crew', [MastersController::class, 'storePerson'])->middleware('permission:people.manage')->name('crew.store');
                Route::put('/crew/{person}', [MastersController::class, 'updatePerson'])->whereNumber('person')->middleware('permission:people.manage')->name('crew.update');
                Route::post('/crew/{person}/active', [MastersController::class, 'activePerson'])->whereNumber('person')->middleware('permission:people.manage')->name('crew.active');

                // Quotations and payments (M31).
                Route::get('/quotations', [DealsController::class, 'quotations'])->middleware('permission:invoice.view')->name('quotations');
                Route::get('/quotations/{quotation}', [DealsController::class, 'quotation'])->whereNumber('quotation')->middleware('permission:invoice.view')->name('quotations.show');
                Route::middleware('permission:invoice.create')->group(function () {
                    Route::post('/quotations', [DealsController::class, 'storeQuotation'])->name('quotations.store');
                    Route::put('/quotations/{quotation}', [DealsController::class, 'updateQuotation'])->whereNumber('quotation')->name('quotations.update');
                    Route::post('/quotations/{quotation}/issue', [DealsController::class, 'issueQuotation'])->whereNumber('quotation')->name('quotations.issue');
                    Route::post('/quotations/{quotation}/decide', [DealsController::class, 'decideQuotation'])->whereNumber('quotation')->name('quotations.decide');
                    Route::post('/quotations/{quotation}/copy', [DealsController::class, 'copyQuotation'])->whereNumber('quotation')->name('quotations.copy');
                });
                Route::get('/payees', [DealsController::class, 'payees'])->middleware('permission:accounts.post')->name('payees');
                Route::post('/payments', [DealsController::class, 'storePayment'])->middleware('permission:accounts.post')->name('payments.store');
                Route::get('/emis', [DealsController::class, 'emis'])->middleware('permission:truck.view')->name('emis');
                Route::post('/trucks/{truck}/emis', [DealsController::class, 'payEmi'])->whereNumber('truck')->middleware('permission:accounts.post')->name('emis.pay');

                // Leave (M40): the requests to approve, a person's leave, the decision once per key.
                Route::get('/leave', [LeaveController::class, 'index'])->middleware('permission:leave.approve')->name('leave');
                Route::post('/leave/{leave}/decide', [LeaveController::class, 'decide'])->whereNumber('leave')->middleware('permission:leave.approve')->name('leave.decide');
                Route::get('/crew/{person}/leave', [LeaveController::class, 'person'])->whereNumber('person')->middleware('permission:people.view')->name('crew.leave');
                Route::get('/crew/{person}/trips', [MastersController::class, 'personTrips'])->whereNumber('person')->middleware('permission:trip.view')->name('crew.trips');
                Route::get('/crew/{person}/driving', [MastersController::class, 'personDriving'])->whereNumber('person')->middleware('permission:trip.view')->name('crew.driving');

                // Fines and e-challans (M48): the register and black points; record and pay once per key.
                Route::get('/fines', [\App\Http\Controllers\Api\Owner\FinesController::class, 'index'])->middleware('permission:truck.view')->name('fines');
                Route::get('/fines/{fine}', [\App\Http\Controllers\Api\Owner\FinesController::class, 'show'])->whereNumber('fine')->middleware('permission:truck.view')->name('fines.show');
                Route::post('/fines', [\App\Http\Controllers\Api\Owner\FinesController::class, 'store'])->middleware('permission:truck.manage')->name('fines.store');
                Route::post('/fines/{fine}/status', [\App\Http\Controllers\Api\Owner\FinesController::class, 'status'])->whereNumber('fine')->middleware('permission:truck.manage')->name('fines.status');
                Route::post('/fines/{fine}/pay', [\App\Http\Controllers\Api\Owner\FinesController::class, 'pay'])->whereNumber('fine')->middleware('permission:accounts.post')->name('fines.pay');

                // Tyres (M38): the store, a truck's tyres by position; the workshop's moves once per key.
                Route::get('/tyres', [TyresController::class, 'index'])->middleware('permission:truck.view')->name('tyres');
                Route::get('/tyres/{tyre}', [TyresController::class, 'show'])->whereNumber('tyre')->middleware('permission:truck.view')->name('tyres.show');
                Route::get('/trucks/{truck}/tyres', [TyresController::class, 'truck'])->whereNumber('truck')->middleware('permission:truck.view')->name('trucks.tyres');
                Route::middleware('permission:complaint.manage')->group(function () {
                    Route::post('/tyres', [TyresController::class, 'store'])->name('tyres.store');
                    Route::post('/tyres/{tyre}/retread', [TyresController::class, 'retread'])->whereNumber('tyre')->name('tyres.retread');
                    Route::post('/tyres/{tyre}/returned', [TyresController::class, 'returned'])->whereNumber('tyre')->name('tyres.returned');
                    Route::post('/tyres/{tyre}/scrap', [TyresController::class, 'scrap'])->whereNumber('tyre')->name('tyres.scrap');
                    Route::post('/trucks/{truck}/tyres', [TyresController::class, 'fit'])->whereNumber('truck')->name('trucks.tyres.fit');
                    Route::post('/trucks/{truck}/tyres/{tyre}/off', [TyresController::class, 'off'])->whereNumber('truck')->whereNumber('tyre')->name('trucks.tyres.off');
                    Route::post('/trucks/{truck}/tyres/{tyre}/move', [TyresController::class, 'move'])->whereNumber('truck')->whereNumber('tyre')->name('trucks.tyres.move');
                });

                // The Business tab of the profile (M28): the company as it prints; edited with settings.company.
                Route::get('/company', [CompanyController::class, 'show'])->name('company');
                Route::post('/company', [CompanyController::class, 'save'])->middleware('permission:settings.company')->name('company.update');
                Route::get('/company/logo', [CompanyController::class, 'logo'])->name('company.logo');

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
                Route::get('/leave', [CrewController::class, 'leave'])->name('leave');
            });
        });
    });
});
