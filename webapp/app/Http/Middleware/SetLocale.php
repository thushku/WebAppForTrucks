<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The screen language (ported from Clinic Ledger): ?lang= on any page
 * (remembered in the session), else the signed-in person's own choice, else
 * the browser's preference among the enabled languages, else English.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $codes = Locales::enabled();
        } catch (\Throwable) {
            // Before the database exists (first install), English only.
            $codes = ['en'];
        }

        if ($request->filled('lang') && in_array($request->query('lang'), $codes, true)) {
            $request->session()->put('locale', $request->query('lang'));
        }

        $person = Auth::guard('web')->user();
        $locale = $request->session()->get('locale')
            ?: ($person && in_array($person->locale, $codes, true) ? $person->locale : null)
            ?: $request->getPreferredLanguage($codes)
            ?: 'en';

        $locale = in_array($locale, $codes, true) ? $locale : 'en';
        app()->setLocale($locale);
        \Carbon\Carbon::setLocale($locale);
        \Carbon\CarbonImmutable::setLocale($locale);

        return $next($request);
    }
}
