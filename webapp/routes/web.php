<?php

use App\Http\Controllers\Accounts;
use App\Http\Controllers\Reports;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\QrLoginController;
use App\Http\Controllers\Auth\SignupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Documents\DocumentShareController;
use App\Http\Controllers\Documents\RequiredTypesController;
use App\Http\Controllers\Invoicing\InvoiceController;
use App\Http\Controllers\Invoicing\PublicDocumentController;
use App\Http\Controllers\Invoicing\ReceiptController;
use App\Http\Controllers\Fleet\ComplaintController;
use App\Http\Controllers\Fleet\EmiController;
use App\Http\Controllers\Fleet\ServiceController;
use App\Http\Controllers\Fleet\TruckController;
use App\Http\Controllers\Fleet\TyreController;
use App\Http\Controllers\Parties\PartyController;
use App\Http\Controllers\People\LeaveController;
use App\Http\Controllers\People\PersonController;
use App\Http\Controllers\Trips\ApprovalController;
use App\Http\Controllers\Trips\ConsignmentController;
use App\Http\Controllers\Trips\HireChallanController;
use App\Http\Controllers\Trips\SettlementController;
use App\Http\Controllers\Trips\TripController;
use App\Http\Controllers\Trips\TripMoneyController;
use App\Http\Controllers\Parties\PartyDetailsController;
use App\Http\Controllers\Parties\RateContractController;
use App\Http\Controllers\Invoicing;
use App\Http\Controllers\Payroll;
use App\Http\Controllers\Trips;
use App\Http\Controllers\Settings;
use App\Http\Controllers\Site;
use Illuminate\Support\Facades\Route;

/*
| The web app for office users, and the public website (M14): "/" is the
| website's home for visitors and the dashboard for people signed in.
*/

Route::get('/', [Site\SiteController::class, 'home'])->name('home');
Route::name('site.')->group(function () {
    Route::get('/features', [Site\SiteController::class, 'features'])->name('features');
    Route::get('/pricing', [Site\SiteController::class, 'pricing'])->name('pricing');
    Route::get('/faq', [Site\SiteController::class, 'faq'])->name('faq');
    Route::get('/about', [Site\SiteController::class, 'about'])->name('about');
    Route::get('/contact', [Site\ContactController::class, 'show'])->name('contact');
    Route::post('/contact', [Site\ContactController::class, 'send'])->middleware('throttle:login')->name('contact.send');
    Route::get('/legal/{page}', [Site\SiteController::class, 'legal'])->whereIn('page', ['terms', 'privacy', 'refund'])->name('legal');
    Route::get('/sitemap.xml', [Site\SiteController::class, 'sitemap'])->name('sitemap');
});

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

    // Sign in with the owner app (QR code, decision P7).
    Route::post('/login/qr', [QrLoginController::class, 'create'])->middleware('throttle:login')->name('login.qr');
    Route::post('/login/qr/poll', [QrLoginController::class, 'poll'])->middleware('throttle:qr-poll')->name('login.qr.poll');

    Route::get('/password/forgot', [PasswordController::class, 'forgot'])->name('password.forgot');
    Route::post('/password/forgot', [PasswordController::class, 'send'])->middleware('throttle:login')->name('password.email');
    Route::get('/password/reset/{token}', [PasswordController::class, 'edit'])->name('password.reset');
    Route::post('/password/reset/{token}', [PasswordController::class, 'update'])->middleware('throttle:login')->name('password.update');
});

// Verification and invitation links work signed in or not.
Route::get('/verify/{token}', [SignupController::class, 'verify'])->name('signup.verify');
Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('invite.show');
Route::post('/invite/{token}', [InvitationController::class, 'accept'])->middleware('throttle:login')->name('invite.accept');
// A shared pack of documents (expiring link; no sign-in).
Route::get('/share/{token}', [DocumentShareController::class, 'show'])->middleware('throttle:60,1')->name('share.show');
Route::get('/share/{token}/file/{file}', [DocumentShareController::class, 'file'])->middleware('throttle:120,1')->whereNumber('file')->name('share.file');

// A bill or receipt by its secure link, for the client (no sign-in; M9).
Route::get('/d/{token}', [PublicDocumentController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:60,1')->name('documents.public');
Route::get('/d/{token}/pdf', [PublicDocumentController::class, 'pdf'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:20,1')->name('documents.public.pdf');
Route::post('/d/{token}/answer', [PublicDocumentController::class, 'answer'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:10,1')->name('documents.public.answer');

// A client's tracking link for an LR (M45; no sign-in).
Route::get('/t/{token}', [\App\Http\Controllers\Trips\TrackingController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:60,1')->name('track');
// A client's own page: their loads with tracking and their open bills (M47; no sign-in).
Route::get('/c/{token}', [\App\Http\Controllers\Parties\ClientPageController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:60,1')->name('client.page');

// What a phone camera opens when the sign-in QR is scanned outside the app.
Route::get('/qr/{token}', [QrLoginController::class, 'scan'])->where('token', '[A-Za-z0-9]{40}')->name('login.qr.scan');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/companies', [LoginController::class, 'showChoice'])->name('company.choose');
    Route::post('/companies', [LoginController::class, 'choose'])->name('company.switch');

    Route::middleware('company')->group(function () {
        Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

        // "+ New" inside the trip form (M25).
        Route::prefix('quick-add')->name('quick.')->middleware('throttle:60,1')->group(function () {
            Route::post('/client', [Trips\QuickAddController::class, 'client'])->middleware('permission:party.manage')->name('client');
            Route::post('/person', [Trips\QuickAddController::class, 'person'])->middleware('permission:people.manage')->name('person');
            Route::post('/truck', [Trips\QuickAddController::class, 'truck'])->middleware('permission:truck.manage')->name('truck');
            Route::post('/place', [Trips\QuickAddController::class, 'place'])->middleware('permission:settings.masters')->name('place');
        });

        // The branch the lists show (M22).
        Route::post('/branch', function (\Illuminate\Http\Request $request, \App\Platform\Tenancy\BranchContext $branches) {
            $branches->select($request->filled('branch') ? (int) $request->input('branch') : null);

            return back();
        })->name('branch.select');

        // My profile and Help (M28).
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->middleware('throttle:20,1')->name('profile.update');
        Route::get('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
        Route::get('/help', [ProfileController::class, 'help'])->name('help');

        // The bell (M20): everyone's own notices in this company.
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/settings', [NotificationController::class, 'settings'])->name('notifications.settings');
        Route::put('/notifications/settings', [NotificationController::class, 'saveSettings'])->name('notifications.settings.update');
        Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->whereNumber('notification')->name('notifications.open');
        Route::post('/notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read-all');

        // Parties: clients, consignors, consignees, suppliers, vendors, brokers (M3).
        Route::prefix('parties')->name('parties.')->group(function () {
            Route::middleware('permission:party.view')->group(function () {
                Route::get('/', [PartyController::class, 'index'])->name('index');
                Route::get('/lookup', [PartyController::class, 'lookup'])->name('lookup');
            });
            Route::middleware('permission:party.manage')->group(function () {
                Route::get('/new', [PartyController::class, 'create'])->name('create');
                Route::post('/', [PartyController::class, 'store'])->name('store');
                Route::get('/suggest', [PartyController::class, 'suggest'])->name('suggest');
                Route::get('/{party}/edit', [PartyController::class, 'edit'])->name('edit');
                Route::put('/{party}', [PartyController::class, 'update'])->name('update');

                Route::post('/{party}/tax-ids', [PartyDetailsController::class, 'storeTaxId'])->name('tax-ids.store');
                Route::post('/{party}/tax-ids/{taxId}/default', [PartyDetailsController::class, 'defaultTaxId'])->name('tax-ids.default');
                Route::post('/{party}/tax-ids/{taxId}/toggle', [PartyDetailsController::class, 'toggleTaxId'])->name('tax-ids.toggle');
                Route::post('/{party}/contacts', [PartyDetailsController::class, 'storeContact'])->name('contacts.store');
                Route::put('/{party}/contacts/{contact}', [PartyDetailsController::class, 'updateContact'])->name('contacts.update');
                Route::delete('/{party}/contacts/{contact}', [PartyDetailsController::class, 'destroyContact'])->name('contacts.destroy');
                Route::post('/{party}/vehicles', [PartyDetailsController::class, 'storeVehicle'])->name('vehicles.store');
                Route::put('/{party}/vehicles/{vehicle}', [PartyDetailsController::class, 'updateVehicle'])->name('vehicles.update');

                Route::get('/{party}/rates/new', [RateContractController::class, 'create'])->name('rates.create');
                Route::post('/{party}/rates', [RateContractController::class, 'store'])->name('rates.store');
                Route::get('/{party}/rates/{contract}', [RateContractController::class, 'edit'])->name('rates.edit');
                Route::put('/{party}/rates/{contract}', [RateContractController::class, 'update'])->name('rates.update');
                Route::post('/{party}/rates/{contract}/approve', [RateContractController::class, 'approve'])->name('rates.approve');
                Route::post('/{party}/rates/{contract}/version', [RateContractController::class, 'newVersion'])->name('rates.version');
                Route::post('/{party}/rates/{contract}/cancel', [RateContractController::class, 'cancel'])->name('rates.cancel');
            });
            // Bank details: changing where a supplier is paid needs the bank permission (fraud control).
            Route::middleware('permission:settings.bank')->group(function () {
                Route::post('/{party}/banks', [PartyDetailsController::class, 'storeBank'])->name('banks.store');
                Route::delete('/{party}/banks/{bank}', [PartyDetailsController::class, 'destroyBank'])->name('banks.destroy');
            });
            // The client's own page link (M47): made and stopped by whoever sends bills.
            Route::middleware('permission:invoice.send')->group(function () {
                Route::post('/{party}/client-page', [\App\Http\Controllers\Parties\ClientPageController::class, 'store'])->name('client-page.store');
                Route::delete('/{party}/client-page', [\App\Http\Controllers\Parties\ClientPageController::class, 'destroy'])->name('client-page.destroy');
            });
            Route::get('/{party}', [PartyController::class, 'show'])->middleware('permission:party.view')->name('show');
        });

        // Trips: full load, part load, mixed; own or hired trucks (M5).
        Route::prefix('trips')->name('trips.')->group(function () {
            Route::get('/', [TripController::class, 'index'])->middleware('permission:trip.view')->name('index');
            Route::middleware('permission:trip.create')->group(function () {
                Route::get('/new', [TripController::class, 'create'])->name('create');
                Route::post('/', [TripController::class, 'store'])->name('store');
                Route::get('/rate', [TripController::class, 'rate'])->name('rate');
                Route::get('/{trip}/edit', [TripController::class, 'edit'])->name('edit');
                Route::put('/{trip}', [TripController::class, 'update'])->name('update');
                Route::get('/{trip}/loads/new', [ConsignmentController::class, 'create'])->name('loads.create');
                Route::post('/{trip}/loads', [ConsignmentController::class, 'store'])->name('loads.store');
                Route::get('/{trip}/loads/{consignment}', [ConsignmentController::class, 'edit'])->whereNumber('consignment')->name('loads.edit');
                Route::put('/{trip}/loads/{consignment}', [ConsignmentController::class, 'update'])->name('loads.update');
                Route::post('/{trip}/loads/{consignment}/cancel', [ConsignmentController::class, 'cancel'])->name('loads.cancel');
                Route::post('/{trip}/stops', [ConsignmentController::class, 'storeStop'])->name('stops.store');
                Route::delete('/{trip}/stops/{stop}', [ConsignmentController::class, 'destroyStop'])->name('stops.destroy');
                Route::post('/{trip}/commissions', [TripMoneyController::class, 'storeCommission'])->name('commissions.store');
                Route::delete('/{trip}/commissions/{commission}', [TripMoneyController::class, 'destroyCommission'])->name('commissions.destroy');
            });
            Route::middleware('permission:trip.assign')->group(function () {
                Route::post('/{trip}/assign', [TripController::class, 'assign'])->name('assign');
                Route::post('/{trip}/start', [TripController::class, 'start'])->name('start');
                Route::post('/{trip}/end', [TripController::class, 'end'])->name('end');
            });
            Route::post('/{trip}/cancel', [TripController::class, 'cancel'])->middleware('permission:trip.cancel')->name('cancel');
            Route::post('/{trip}/advances', [TripMoneyController::class, 'storeAdvance'])->middleware('permission:trip.settle')->name('advances.store');
            Route::post('/{trip}/consignments/{consignment}/track', [\App\Http\Controllers\Trips\TrackingController::class, 'share'])->whereNumber('consignment')->middleware('permission:trip.view')->name('track');
            Route::middleware('permission:trip.settle')->group(function () {
                Route::post('/{trip}/settle', [SettlementController::class, 'settleWithoutCrew'])->name('settle.all');
                Route::post('/{trip}/settle/{person}', [SettlementController::class, 'settle'])->name('settle');
                Route::post('/{trip}/close', [SettlementController::class, 'close'])->name('close');
            });
            Route::post('/{trip}/reopen', [SettlementController::class, 'reopen'])->middleware('permission:trip.reopen')->name('reopen');
            Route::middleware('permission:trip.office_entries')->group(function () {
                Route::post('/{trip}/office', [TripMoneyController::class, 'storeOffice'])->name('office.store');
                Route::put('/{trip}/office/{entry}', [TripMoneyController::class, 'updateOffice'])->name('office.update');
                Route::post('/{trip}/office/{entry}/cancel', [TripMoneyController::class, 'cancelOffice'])->name('office.cancel');
            });
            Route::middleware('permission:hire.manage')->group(function () {
                Route::get('/{trip}/hire/new', [HireChallanController::class, 'create'])->name('hire.create');
                Route::post('/{trip}/hire', [HireChallanController::class, 'store'])->name('hire.store');
                Route::get('/{trip}/hire/{challan}', [HireChallanController::class, 'edit'])->name('hire.edit');
                Route::put('/{trip}/hire/{challan}', [HireChallanController::class, 'update'])->name('hire.update');
                Route::post('/{trip}/hire/{challan}/issue', [HireChallanController::class, 'issue'])->name('hire.issue');
                Route::post('/{trip}/hire/{challan}/lines', [HireChallanController::class, 'storeLine'])->name('hire.lines.store');
            });
            Route::get('/{trip}/photos/{photo}', [TripController::class, 'photo'])->middleware('permission:trip.view')->name('photo');
            Route::get('/{trip}/loads/{consignment}/lr', [ConsignmentController::class, 'printLr'])->middleware('permission:lr.view,trip.view')->name('loads.lr');
            Route::get('/{trip}/loads/{consignment}/lr.pdf', [ConsignmentController::class, 'pdfLr'])->middleware('permission:lr.view,trip.view')->name('loads.lr.pdf');
            Route::get('/report', [TripController::class, 'report'])->middleware('permission:trip.view')->name('report');
            Route::get('/{trip}', [TripController::class, 'show'])->middleware('permission:trip.view')->name('show');
        });

        // Bills: invoices, supplementary bills, credit and debit notes (M9).
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::middleware('permission:invoice.view')->group(function () {
                Route::get('/', [InvoiceController::class, 'index'])->name('index');
                Route::get('/{invoice}', [InvoiceController::class, 'show'])->whereNumber('invoice')->name('show');
                Route::get('/{invoice}/print', [InvoiceController::class, 'print'])->name('print');
                Route::get('/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('pdf');
            });
            Route::middleware('permission:invoice.create')->group(function () {
                Route::get('/new', [InvoiceController::class, 'create'])->name('create');
                Route::post('/', [InvoiceController::class, 'store'])->name('store');
                Route::get('/{invoice}/edit', [InvoiceController::class, 'edit'])->name('edit');
                Route::put('/{invoice}', [InvoiceController::class, 'update'])->name('update');
                Route::post('/{invoice}/against', [InvoiceController::class, 'against'])->name('against');
            });
            Route::post('/{invoice}/finalise', [InvoiceController::class, 'finalise'])->middleware('permission:invoice.finalise')->name('finalise');
            Route::post('/{invoice}/void', [InvoiceController::class, 'void'])->middleware('permission:invoice.create,invoice.void')->name('void');
            Route::post('/{invoice}/submit', [InvoiceController::class, 'submit'])->middleware('permission:invoice.send')->name('submit');
            Route::post('/{invoice}/shared', [InvoiceController::class, 'shared'])->middleware('permission:invoice.send')->name('shared');
            Route::post('/{invoice}/email', [InvoiceController::class, 'email'])->middleware(['permission:invoice.send', 'throttle:30,60'])->name('email');
        });

        // Quotations / rate confirmations (M21).
        Route::prefix('quotations')->name('quotations.')->group(function () {
            Route::middleware('permission:invoice.view')->group(function () {
                Route::get('/', [Invoicing\QuotationController::class, 'index'])->name('index');
                Route::get('/{quotation}', [Invoicing\QuotationController::class, 'show'])->whereNumber('quotation')->name('show');
                Route::get('/{quotation}/print', [Invoicing\QuotationController::class, 'print'])->whereNumber('quotation')->name('print');
                Route::get('/{quotation}/pdf', [Invoicing\QuotationController::class, 'pdf'])->whereNumber('quotation')->name('pdf');
            });
            Route::middleware('permission:invoice.create')->group(function () {
                Route::get('/new', [Invoicing\QuotationController::class, 'create'])->name('create');
                Route::post('/', [Invoicing\QuotationController::class, 'store'])->name('store');
                Route::get('/{quotation}/edit', [Invoicing\QuotationController::class, 'edit'])->whereNumber('quotation')->name('edit');
                Route::put('/{quotation}', [Invoicing\QuotationController::class, 'update'])->whereNumber('quotation')->name('update');
                Route::post('/{quotation}/issue', [Invoicing\QuotationController::class, 'issue'])->whereNumber('quotation')->name('issue');
                Route::post('/{quotation}/decide', [Invoicing\QuotationController::class, 'decide'])->whereNumber('quotation')->name('decide');
                Route::post('/{quotation}/copy', [Invoicing\QuotationController::class, 'copy'])->whereNumber('quotation')->name('copy');
                Route::post('/{quotation}/contract', [Invoicing\QuotationController::class, 'contract'])->whereNumber('quotation')->name('contract');
            });
            Route::post('/{quotation}/email', [Invoicing\QuotationController::class, 'email'])->whereNumber('quotation')->middleware(['permission:invoice.send', 'throttle:30,60'])->name('email');
            Route::get('/{quotation}/whatsapp', [Invoicing\QuotationController::class, 'whatsapp'])->whereNumber('quotation')->middleware('permission:invoice.send')->name('whatsapp');
        });

        // Pre-printed LR books (M21).
        Route::prefix('lr-books')->name('lr-books.')->group(function () {
            Route::middleware('permission:lr.view')->group(function () {
                Route::get('/', [Trips\LrBookController::class, 'index'])->name('index');
                Route::get('/{book}', [Trips\LrBookController::class, 'show'])->whereNumber('book')->name('show');
            });
            Route::middleware('permission:lr.manage')->group(function () {
                Route::post('/', [Trips\LrBookController::class, 'store'])->name('store');
                Route::post('/{book}/leaves', [Trips\LrBookController::class, 'mark'])->whereNumber('book')->name('mark');
                Route::delete('/{book}/leaves/{leaf}', [Trips\LrBookController::class, 'unmark'])->whereNumber(['book', 'leaf'])->name('unmark');
                Route::post('/{book}/status', [Trips\LrBookController::class, 'status'])->whereNumber('book')->name('status');
            });
        });

        // Money received (M9).
        Route::prefix('receipts')->name('receipts.')->group(function () {
            Route::middleware('permission:payment.view')->group(function () {
                Route::get('/', [ReceiptController::class, 'index'])->name('index');
                Route::get('/{receipt}', [ReceiptController::class, 'show'])->whereNumber('receipt')->name('show');
                Route::get('/{receipt}/print', [ReceiptController::class, 'print'])->name('print');
                Route::get('/{receipt}/pdf', [ReceiptController::class, 'pdf'])->name('pdf');
            });
            Route::middleware('permission:payment.record')->group(function () {
                Route::get('/new', [ReceiptController::class, 'create'])->name('create');
                Route::post('/', [ReceiptController::class, 'store'])->name('store');
            });
            Route::post('/{receipt}/void', [ReceiptController::class, 'void'])->middleware('permission:payment.void')->name('void');
            Route::post('/{receipt}/email', [ReceiptController::class, 'email'])->middleware(['permission:invoice.send,payment.record', 'throttle:30,60'])->name('email');
        });

        // Where the trucks are (M12).
        Route::middleware('permission:map.view')->group(function () {
            Route::get('/map', [Reports\MapController::class, 'index'])->name('map.index');
            Route::get('/map/positions', [Reports\MapController::class, 'positions'])->name('map.positions');
        });
        Route::get('/trips/{trip}/route', [Reports\MapController::class, 'route'])->middleware('permission:trip.view,map.view')->name('trips.route');

        Route::get('/reports', [Reports\ReportController::class, 'index'])->middleware('permission:report.view')->name('reports.index');

        // The ledgers: clients, suppliers and vendors, crew (M11).
        Route::prefix('ledgers')->name('ledgers.')->group(function () {
            Route::get('/', [Accounts\LedgerController::class, 'index'])->middleware('permission:ledger.view')->name('index');
            Route::middleware('permission:ledger.view,accounts.view')->group(function () {
                Route::get('/{ledger}', [Accounts\LedgerController::class, 'show'])->whereNumber('ledger')->name('show');
                Route::get('/{ledger}/export', [Accounts\LedgerController::class, 'export'])->whereNumber('ledger')->name('export');
                Route::get('/{ledger}/print', [Accounts\LedgerController::class, 'print'])->whereNumber('ledger')->name('print');
                Route::get('/{ledger}/pdf', [Accounts\LedgerController::class, 'pdf'])->whereNumber('ledger')->name('pdf');
                Route::post('/{ledger}/email', [Accounts\LedgerController::class, 'email'])->whereNumber('ledger')->middleware('throttle:30,60')->name('email');
            });
        });

        // The book (M11).
        Route::prefix('accounts')->name('accounts.')->middleware('permission:accounts.view')->group(function () {
            Route::get('/', [Accounts\BooksController::class, 'index'])->name('index');
            Route::get('/day-book', [Accounts\BooksController::class, 'dayBook'])->name('day-book');
            Route::get('/trial-balance', [Accounts\BooksController::class, 'trialBalance'])->name('trial-balance');
            Route::get('/profit-loss', [Accounts\BooksController::class, 'profitLoss'])->name('profit-loss');
            Route::get('/balance-sheet', [Accounts\BooksController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('/trucks', [Accounts\BooksController::class, 'trucks'])->name('trucks');
            Route::get('/chart', [Accounts\BooksController::class, 'chart'])->name('chart');
            Route::get('/vouchers/{voucher}', [Accounts\BooksController::class, 'voucher'])->whereNumber('voucher')->name('vouchers.show');
            Route::get('/vouchers/{voucher}/print', [Accounts\BooksController::class, 'printVoucher'])->whereNumber('voucher')->name('vouchers.print');
            Route::get('/ageing', [Accounts\ReportsController::class, 'ageing'])->name('ageing');
            Route::get('/tax', [Accounts\ReportsController::class, 'tax'])->name('tax');
            Route::get('/tds', [Accounts\ReportsController::class, 'tds'])->name('tds');
            Route::get('/tally', [Accounts\ReportsController::class, 'tally'])->middleware('permission:data.export,statement.export')->name('tally');
            Route::get('/bank', [Accounts\BankController::class, 'index'])->name('bank');
            // FASTag and fuel-card statements (M44).
            Route::get('/cards', [Accounts\CardController::class, 'index'])->name('cards');
            Route::get('/cards/{statement}', [Accounts\CardController::class, 'show'])->whereNumber('statement')->name('cards.show');
            Route::middleware('permission:accounts.post')->group(function () {
                Route::post('/chart', [Accounts\BooksController::class, 'storeLedger'])->name('chart.store');
                Route::get('/journal/new', [Accounts\JournalController::class, 'create'])->name('journal.create');
                Route::post('/journal', [Accounts\JournalController::class, 'store'])->name('journal.store');
                Route::get('/payments/new', [Accounts\PaymentController::class, 'create'])->name('payments.create');
                Route::post('/payments', [Accounts\PaymentController::class, 'store'])->name('payments.store');
                Route::post('/vouchers/{voucher}/reverse', [Accounts\JournalController::class, 'reverse'])->whereNumber('voucher')->name('vouchers.reverse');
                Route::get('/opening', [Accounts\OpeningController::class, 'edit'])->name('opening');
                Route::put('/opening', [Accounts\OpeningController::class, 'update'])->name('opening.update');
                Route::post('/opening/import', [Accounts\OpeningController::class, 'import'])->name('opening.import');
                Route::post('/bank/import', [Accounts\BankController::class, 'import'])->middleware('throttle:20,60')->name('bank.import');
                Route::post('/bank/auto', [Accounts\BankController::class, 'auto'])->name('bank.auto');
                Route::post('/bank/lines/{line}', [Accounts\BankController::class, 'match'])->whereNumber('line')->name('bank.match');
                Route::post('/cards/import', [Accounts\CardController::class, 'import'])->middleware('throttle:20,60')->name('cards.import');
                Route::post('/cards/lines/{line}', [Accounts\CardController::class, 'line'])->whereNumber('line')->name('cards.line');
            });
            Route::middleware('permission:accounts.lock')->group(function () {
                Route::get('/lock', [Accounts\LockController::class, 'edit'])->name('lock');
                Route::put('/lock', [Accounts\LockController::class, 'update'])->name('lock.update');
            });
        });

        // The crew's entries waiting for the office (M7).
        Route::prefix('approvals')->name('approvals.')->middleware('permission:event.verify')->group(function () {
            Route::get('/', [ApprovalController::class, 'index'])->name('index');
            Route::post('/many', [ApprovalController::class, 'approveMany'])->name('many');
            Route::post('/{event}', [ApprovalController::class, 'decide'])->name('decide');
            Route::get('/{event}/photos/{photo}', [ApprovalController::class, 'photo'])->name('photo');
        });

        // Document Manager: every owner's papers, expiry, renewals, shares (M3).
        Route::prefix('documents')->name('documents.')->group(function () {
            Route::middleware('permission:document.view')->group(function () {
                Route::get('/', [DocumentController::class, 'index'])->name('index');
                Route::get('/required', [RequiredTypesController::class, 'index'])->name('required');
                Route::get('/of/{ownerType}/{ownerId?}', [DocumentController::class, 'owner'])->whereIn('ownerType', ['truck', 'person', 'company', 'party'])->whereNumber('ownerId')->name('owner');
                Route::get('/file/{file}', [DocumentController::class, 'file'])->name('file');
            });
            Route::middleware('permission:document.manage')->group(function () {
                Route::get('/of/{ownerType}/{ownerId}/new', [DocumentController::class, 'create'])->whereIn('ownerType', ['truck', 'person', 'company', 'party'])->whereNumber('ownerId')->name('create');
                Route::post('/of/{ownerType}/{ownerId}', [DocumentController::class, 'store'])->whereIn('ownerType', ['truck', 'person', 'company', 'party'])->whereNumber('ownerId')->name('store');
                Route::get('/{document}/edit', [DocumentController::class, 'edit'])->name('edit');
                Route::put('/{document}', [DocumentController::class, 'update'])->name('update');
                Route::get('/{document}/renew', [DocumentController::class, 'edit'])->name('renew.form');
                Route::post('/{document}/renew', [DocumentController::class, 'renew'])->name('renew');
                Route::post('/{document}/snooze', [DocumentController::class, 'snooze'])->name('snooze');
                Route::post('/shares', [DocumentShareController::class, 'store'])->name('shares.store');
                Route::post('/shares/{share}/revoke', [DocumentShareController::class, 'revoke'])->name('shares.revoke');
                Route::post('/required', [RequiredTypesController::class, 'store'])->name('required.store');
                Route::delete('/required/{rule}', [RequiredTypesController::class, 'destroy'])->name('required.destroy');
            });
            Route::get('/{document}', [DocumentController::class, 'show'])->middleware('permission:document.view')->name('show');
        });

        // Trucks, their complaints and service (M4).
        Route::prefix('trucks')->name('trucks.')->group(function () {
            Route::get('/', [TruckController::class, 'index'])->middleware('permission:truck.view')->name('index');
            // Truck loans and EMIs (M35): see with truck.view, pay with accounts.post (it is a payment voucher).
            Route::get('/emis', [EmiController::class, 'index'])->middleware('permission:truck.view')->name('emis');
            Route::post('/{truck}/emis', [EmiController::class, 'pay'])->middleware('permission:accounts.post')->name('emis.pay');
            Route::middleware('permission:truck.manage')->group(function () {
                Route::get('/new', [TruckController::class, 'create'])->name('create');
                Route::post('/', [TruckController::class, 'store'])->name('store');
                Route::get('/{truck}/edit', [TruckController::class, 'edit'])->name('edit');
                Route::put('/{truck}', [TruckController::class, 'update'])->name('update');
                Route::post('/{truck}/status', [TruckController::class, 'status'])->name('status');
            });
            Route::post('/{truck}/complaints', [ComplaintController::class, 'store'])->middleware('permission:complaint.raise,complaint.manage')->name('complaints.store');
            Route::middleware('permission:complaint.manage')->group(function () {
                Route::post('/{truck}/schedules', [ServiceController::class, 'storeSchedule'])->name('schedules.store');
                Route::put('/{truck}/schedules/{schedule}', [ServiceController::class, 'updateSchedule'])->name('schedules.update');
                Route::get('/{truck}/service/new', [ServiceController::class, 'create'])->name('service.create');
                Route::post('/{truck}/service', [ServiceController::class, 'store'])->name('service.store');
                Route::get('/{truck}/service/{job}', [ServiceController::class, 'edit'])->name('service.edit');
                Route::put('/{truck}/service/{job}', [ServiceController::class, 'update'])->name('service.update');
                Route::post('/{truck}/service/{job}/done', [ServiceController::class, 'complete'])->name('service.complete');
                Route::post('/{truck}/service/{job}/cancel', [ServiceController::class, 'cancel'])->name('service.cancel');
            });
            // Tyres (M38): the store and register; fit, take off and move by position on the truck's Tyres tab.
            Route::get('/tyres', [TyreController::class, 'index'])->middleware('permission:truck.view')->name('tyres');
            Route::get('/tyres/{tyre}', [TyreController::class, 'show'])->middleware('permission:truck.view')->name('tyres.show');
            Route::middleware('permission:complaint.manage')->group(function () {
                Route::post('/tyres', [TyreController::class, 'store'])->name('tyres.store');
                Route::put('/tyres/{tyre}', [TyreController::class, 'update'])->name('tyres.update');
                Route::post('/tyres/{tyre}/retread', [TyreController::class, 'retread'])->name('tyres.retread');
                Route::post('/tyres/{tyre}/returned', [TyreController::class, 'returned'])->name('tyres.returned');
                Route::post('/tyres/{tyre}/scrap', [TyreController::class, 'scrap'])->name('tyres.scrap');
                Route::post('/{truck}/tyres', [TyreController::class, 'fit'])->name('tyres.fit');
                Route::post('/{truck}/tyres/{tyre}/off', [TyreController::class, 'off'])->name('tyres.off');
                Route::post('/{truck}/tyres/{tyre}/move', [TyreController::class, 'move'])->name('tyres.move');
            });
            Route::get('/{truck}', [TruckController::class, 'show'])->middleware('permission:truck.view')->name('show');
        });

        Route::prefix('complaints')->name('complaints.')->group(function () {
            Route::get('/', [ComplaintController::class, 'index'])->middleware('permission:complaint.manage,truck.view')->name('index');
            Route::get('/{complaint}', [ComplaintController::class, 'show'])->middleware('permission:complaint.manage,truck.view')->name('show');
            Route::put('/{complaint}', [ComplaintController::class, 'update'])->middleware('permission:complaint.manage')->name('update');
        });

        // People: drivers, helpers, staff; crew-app activation codes (M4).
        Route::prefix('people')->name('people.')->group(function () {
            Route::get('/', [PersonController::class, 'index'])->middleware('permission:people.view')->name('index');
            // Leave (M40): see and record with leave.approve or people.manage; approve, turn down and cancel with leave.approve.
            Route::get('/leave', [LeaveController::class, 'index'])->middleware('permission:leave.approve,people.manage')->name('leave');
            Route::post('/leave', [LeaveController::class, 'store'])->middleware('permission:leave.approve,people.manage')->name('leave.store');
            Route::post('/leave/{leave}/decide', [LeaveController::class, 'decide'])->whereNumber('leave')->middleware('permission:leave.approve')->name('leave.decide');
            Route::post('/leave/{leave}/cancel', [LeaveController::class, 'cancel'])->whereNumber('leave')->middleware('permission:leave.approve')->name('leave.cancel');
            Route::middleware('permission:people.manage')->group(function () {
                Route::get('/new', [PersonController::class, 'create'])->name('create');
                Route::post('/', [PersonController::class, 'store'])->name('store');
                Route::get('/{person}/edit', [PersonController::class, 'edit'])->name('edit');
                Route::put('/{person}', [PersonController::class, 'update'])->name('update');
                Route::post('/{person}/activate', [PersonController::class, 'activate'])->middleware('throttle:10,1')->name('activate');
            });
            Route::post('/{person}/reveal-id', [PersonController::class, 'revealId'])->middleware('permission:people.id_documents')->name('reveal-id');
            // Quick cash given to or spent by a crew member (M27), into their khata.
            Route::post('/{person}/cash', [PersonController::class, 'cash'])->middleware('permission:trip.settle')->name('cash');
            Route::get('/{person}', [PersonController::class, 'show'])->middleware('permission:people.view')->name('show');
        });

        // Payroll (M16): pay runs for any period, payslips, payments.
        Route::prefix('payroll')->name('payroll.')->group(function () {
            Route::get('/', [Payroll\PayrollController::class, 'index'])->middleware('permission:payroll.view')->name('index');
            Route::post('/', [Payroll\PayrollController::class, 'store'])->middleware('permission:payroll.run')->name('store');
            Route::get('/{run}', [Payroll\PayrollController::class, 'show'])->middleware('permission:payroll.view')->name('show');
            Route::get('/{run}/slips/{slip}', [Payroll\PayrollController::class, 'slip'])->whereNumber('slip')->middleware('permission:payroll.view')->name('slip');
            Route::middleware('permission:payroll.run')->group(function () {
                Route::post('/{run}/recalculate', [Payroll\PayrollController::class, 'recalculate'])->name('recalculate');
                Route::post('/{run}/approve', [Payroll\PayrollController::class, 'approve'])->name('approve');
                Route::post('/{run}/cancel', [Payroll\PayrollController::class, 'cancel'])->name('cancel');
                Route::post('/{run}/slips/{slip}/lines', [Payroll\PayrollController::class, 'addLine'])->whereNumber('slip')->name('lines.store');
                Route::delete('/{run}/slips/{slip}/lines/{index}', [Payroll\PayrollController::class, 'removeLine'])->whereNumber(['slip', 'index'])->name('lines.destroy');
                Route::delete('/{run}/slips/{slip}', [Payroll\PayrollController::class, 'removeSlip'])->whereNumber('slip')->name('slips.destroy');
                Route::post('/{run}/slips/{slip}/pay', [Payroll\PayrollController::class, 'pay'])->whereNumber('slip')->name('pay');
            });
        });

        // Getting started: the first steps of a new company (M14).
        Route::middleware('permission:settings.company')->group(function () {
            Route::get('/welcome', [Settings\SetupController::class, 'show'])->name('welcome');
            Route::post('/welcome/company', [Settings\SetupController::class, 'company'])->name('welcome.company');
            Route::post('/welcome/done', [Settings\SetupController::class, 'finish'])->name('welcome.finish');
        });

        // Your data (M17): the export and the deletion countdown, both open while the company is read-only.
        Route::get('/settings/data', [Settings\DataController::class, 'show'])->middleware('permission:data.export,company.delete')->name('settings.data');
        Route::get('/settings/data/export', [Settings\DataController::class, 'export'])->middleware(['permission:data.export', 'throttle:3,60'])->name('settings.data.export');
        Route::post('/settings/data/delete', [Settings\DataController::class, 'requestDeletion'])->middleware(['permission:company.delete', 'throttle:5,60'])->name('company.deletion.request');
        Route::post('/settings/data/delete/cancel', [Settings\DataController::class, 'cancelDeletion'])->middleware('permission:company.delete')->name('company.deletion.cancel');

        // Plan and billing sits with the settings but keeps its own name: billing.* stays open while the company is read-only.
        Route::get('/settings/billing', Settings\BillingController::class)->middleware('permission:billing.view')->name('billing.index');

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', Settings\SettingsController::class)->name('index');

            Route::middleware('permission:settings.company')->group(function () {
                Route::get('/company', [Settings\CompanyProfileController::class, 'edit'])->name('company');
                Route::put('/company', [Settings\CompanyProfileController::class, 'update'])->name('company.update');
                Route::get('/branches', [Settings\BranchController::class, 'index'])->name('branches');
                Route::post('/branches', [Settings\BranchController::class, 'store'])->name('branches.store');
                Route::get('/branches/{branch}', [Settings\BranchController::class, 'edit'])->name('branches.edit');
                Route::put('/branches/{branch}', [Settings\BranchController::class, 'update'])->name('branches.update');
            });
            // Any member may see the logo (it prints on their documents).
            Route::get('/company/logo', [Settings\CompanyProfileController::class, 'logo'])->name('company.logo');

            // The words sent with bills, receipts and reminders, and the reminder days (M18).
            Route::middleware('permission:settings.templates')->group(function () {
                Route::get('/messages', [Settings\MessagesController::class, 'edit'])->name('messages');
                Route::put('/messages', [Settings\MessagesController::class, 'update'])->name('messages.update');
            });

            Route::middleware('permission:settings.rules')->group(function () {
                Route::get('/rules', [Settings\RulesController::class, 'edit'])->name('rules');
                Route::put('/rules', [Settings\RulesController::class, 'update'])->name('rules.update');
            });

            Route::get('/team', [Settings\TeamController::class, 'index'])->middleware('permission:user.invite,user.edit,user.remove,role.grant,security.unlock')->name('team');
            Route::post('/team', [Settings\TeamController::class, 'invite'])->middleware('permission:user.invite')->name('team.invite');
            Route::get('/team/{member}', [Settings\TeamController::class, 'edit'])->middleware('permission:user.edit,role.grant')->name('team.edit');
            Route::put('/team/{member}', [Settings\TeamController::class, 'update'])->middleware('permission:user.edit,role.grant')->name('team.update');
            Route::post('/team/{member}/suspend', [Settings\TeamController::class, 'suspend'])->middleware('permission:user.edit')->name('team.suspend');
            Route::post('/team/{member}/reactivate', [Settings\TeamController::class, 'reactivate'])->middleware('permission:user.edit')->name('team.reactivate');
            Route::post('/team/{member}/resend', [Settings\TeamController::class, 'resend'])->middleware('permission:user.invite')->name('team.resend');
            Route::post('/team/{member}/unlock', [Settings\TeamController::class, 'unlock'])->middleware('permission:security.unlock')->name('team.unlock');
            Route::delete('/team/{member}', [Settings\TeamController::class, 'remove'])->middleware('permission:user.remove')->name('team.remove');

            Route::get('/roles', [Settings\RoleController::class, 'index'])->middleware('permission:role.define,role.grant')->name('roles');
            Route::post('/roles', [Settings\RoleController::class, 'store'])->middleware('permission:role.define')->name('roles.store');
            Route::get('/roles/{role}', [Settings\RoleController::class, 'edit'])->middleware('permission:role.define,role.grant')->name('roles.edit');
            Route::put('/roles/{role}', [Settings\RoleController::class, 'update'])->middleware('permission:role.define')->name('roles.update');

            Route::middleware('permission:settings.masters')->group(function () {
                Route::get('/places', [Settings\PlaceController::class, 'index'])->name('places');
                Route::post('/places', [Settings\PlaceController::class, 'store'])->name('places.store');
                Route::get('/places/suggest', [Settings\PlaceController::class, 'suggest'])->name('places.suggest');
                Route::get('/places/{place}', [Settings\PlaceController::class, 'edit'])->name('places.edit');
                Route::put('/places/{place}', [Settings\PlaceController::class, 'update'])->name('places.update');
                Route::get('/lists/{list?}', [Settings\MasterListController::class, 'index'])->name('lists');
                Route::post('/lists/{list}', [Settings\MasterListController::class, 'store'])->name('lists.store');
                Route::put('/lists/item/{item}', [Settings\MasterListController::class, 'update'])->name('lists.update');
            });

            Route::middleware('permission:settings.numbering')->group(function () {
                Route::get('/numbering', [Settings\NumberingController::class, 'index'])->name('numbering');
                Route::put('/numbering/{sequence}', [Settings\NumberingController::class, 'update'])->name('numbering.update');
                Route::post('/numbering/{sequence}/preview', [Settings\NumberingController::class, 'preview'])->name('numbering.preview');
            });

            Route::middleware('permission:settings.bank')->group(function () {
                Route::get('/banks', [Settings\BankAccountController::class, 'index'])->name('banks');
                Route::post('/banks', [Settings\BankAccountController::class, 'store'])->name('banks.store');
                Route::put('/banks/{account}', [Settings\BankAccountController::class, 'update'])->name('banks.update');
            });

            Route::middleware('permission:settings.templates')->group(function () {
                Route::get('/templates', [Settings\TemplateController::class, 'index'])->name('templates');
                Route::post('/templates', [Settings\TemplateController::class, 'store'])->name('templates.store');
                Route::post('/templates/import', [Settings\TemplateController::class, 'import'])->name('templates.import');
                Route::post('/templates/preview/{family}', [Settings\TemplateController::class, 'preview'])->name('templates.preview');
                Route::get('/templates/{template}', [Settings\TemplateController::class, 'edit'])->name('templates.edit');
                Route::put('/templates/{template}', [Settings\TemplateController::class, 'update'])->name('templates.update');
                Route::put('/templates/{template}/assign', [Settings\TemplateController::class, 'assign'])->name('templates.assign');
                Route::post('/templates/{template}/duplicate', [Settings\TemplateController::class, 'duplicate'])->name('templates.duplicate');
                Route::post('/templates/{template}/default', [Settings\TemplateController::class, 'makeDefault'])->name('templates.default');
                Route::post('/templates/{template}/rollback', [Settings\TemplateController::class, 'rollback'])->name('templates.rollback');
                Route::get('/templates/{template}/export', [Settings\TemplateController::class, 'export'])->name('templates.export');
                Route::delete('/templates/{template}', [Settings\TemplateController::class, 'destroy'])->name('templates.destroy');
            });

            Route::middleware('permission:settings.tax')->group(function () {
                Route::get('/tax', [Settings\TaxController::class, 'index'])->name('tax');
                Route::put('/tax/rules', [Settings\TaxController::class, 'saveRules'])->name('tax.rules');
                Route::post('/tax/registrations', [Settings\TaxController::class, 'storeRegistration'])->name('tax.registrations.store');
                Route::put('/tax/registrations/{registration}', [Settings\TaxController::class, 'updateRegistration'])->name('tax.registrations.update');
                Route::post('/tax/registrations/{registration}/years', [Settings\TaxController::class, 'storeYear'])->name('tax.years.store');
                Route::post('/tax/codes/{code}/rates', [Settings\TaxController::class, 'storeRate'])->name('tax.rates.store');
                Route::put('/tax/rules/{rule}', [Settings\TaxController::class, 'updateRule'])->name('tax.rule.update');
            });

            Route::middleware('permission:security.configure')->group(function () {
                Route::get('/security', [Settings\SecurityController::class, 'edit'])->name('security');
                Route::put('/security', [Settings\SecurityController::class, 'update'])->name('security.update');
            });

            Route::get('/activity', [Settings\ActivityController::class, 'index'])->middleware('permission:audit.view')->name('activity');
        });
    });
});
