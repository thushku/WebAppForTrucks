<?php

namespace App\Providers;

use App\Models\Account;
use App\Platform\Authorization\PermissionCatalogue;
use App\Platform\Authorization\PermissionResolver;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One per request (or job): the company in context and the memo of
        // who may do what.
        $this->app->singleton(CompanyContext::class);
        $this->app->singleton(PermissionResolver::class);
        $this->app->singleton(PlatformSettings::class);
        $this->app->singleton(\App\Modules\Settings\Services\CompanySettings::class);

        // Where pushes go (M20): Firebase when its key is set, else the log.
        $this->app->bind(\App\Platform\Notifications\Push\PushGateway::class, function () {
            return match (config('services.fcm.driver')) {
                'none' => new \App\Platform\Notifications\Push\NullGateway(),
                'fcm' => \App\Platform\Notifications\Push\FcmGateway::fromConfig() ?? tap(new \App\Platform\Notifications\Push\LogGateway(),
                    fn () => \Illuminate\Support\Facades\Log::warning('PUSH_DRIVER is fcm but FIREBASE_CREDENTIALS is not a readable service-account key; pushes go to the log.')),
                default => new \App\Platform\Notifications\Push\LogGateway(),
            };
        });
    }

    public function boot(): void
    {
        // Catch lazy loading, silently discarded attributes and missing
        // attributes while developing; never in production.
        Model::shouldBeStrict(! $this->app->isProduction());

        // The book follows the operations: every money record posts (or reverses) its voucher (M11).
        \App\Modules\Accounts\Services\BookKeeper::register();

        // Every permission code is also a Gate ability, so Blade can say
        // @can('trip.create'). Unknown abilities fall through to policies.
        Gate::before(function ($user, string $ability) {
            if ($user instanceof Account && PermissionCatalogue::exists($ability)) {
                return $user->hasPermission($ability);
            }

            return null;
        });

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Sign-in forms: per address and per computer, on top of the captcha
        // and the lock ladder.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('login:ip:' . $request->ip()),
            Limit::perMinute(5)->by('login:id:' . mb_strtolower((string) $request->input('login', $request->input('email')))),
        ]);

        // One-time codes: sending and checking.
        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(10)->by('otp:' . $request->ip()));

        // The sign-in page asking whether its phone code was scanned (every 2 s).
        RateLimiter::for('qr-poll', fn (Request $request) => Limit::perMinute(60)->by('qr:' . $request->ip()));

        // The phone apps: per token, generous enough for a sync after a day offline.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by('api:' . ($request->user()?->id ?: $request->ip())));
    }
}
