<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The super-admin console lives under a secret path (config/platform.php),
        // never at a guessable /admin — the website guideline's "obfuscated routes".
        then: function () {
            Route::middleware('web')
                ->prefix(config('platform.path'))
                ->name('platform.')
                ->group(base_path('routes/platform.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // The interface language, from ?lang=, the person, or the browser.
            App\Http\Middleware\SetLocale::class,
            // Which company this request works in. It runs BEFORE any
            // permission check, because permissions are held per company.
            App\Http\Middleware\ResolveCompany::class,
        ]);

        // The company must be in context before route-model binding loads a
        // company-owned row (a branch, a place), or its scope has no company.
        $middleware->prependToPriorityList(
            Illuminate\Routing\Middleware\SubstituteBindings::class,
            App\Http\Middleware\ResolveCompany::class,
        );
        $middleware->prependToPriorityList(
            Illuminate\Routing\Middleware\SubstituteBindings::class,
            App\Http\Middleware\ResolveApiCompany::class,
        );

        // Security headers on every response: pages, API answers and errors alike.
        $middleware->append(App\Http\Middleware\SecurityHeaders::class);

        // A tunnel or reverse proxy on this machine (for testing on a real phone,
        // which needs https) may forward requests; only local proxies are trusted.
        $middleware->trustProxies(at: explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')));

        $middleware->alias([
            'company'    => App\Http\Middleware\RequireCompany::class,
            'permission' => App\Http\Middleware\RequirePermission::class,
            'api.company' => App\Http\Middleware\ResolveApiCompany::class,
        ]);

        // Guests of the console go to the console's own sign-in; everybody else to the app's.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is(config('platform.path'), config('platform.path').'/*')
            ? route('platform.login')
            : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is(config('platform.path'), config('platform.path').'/*')
            ? route('platform.home')
            : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The phone apps always get JSON, never an HTML error page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
