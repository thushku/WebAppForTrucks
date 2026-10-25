<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response (ported from Clinic Ledger).
 *
 *  · Nobody else's page may frame ours (clickjacking).
 *  · Browsers must not guess file types (a PDF read as a page).
 *  · Our addresses are not handed to other sites in the Referer.
 *  · Camera for our own pages only (a photo of a receipt); microphone,
 *    payment and the rest, never.
 *  · A Content-Security-Policy: scripts only from our own files, each tagged
 *    with a nonce made fresh for this response, so a script slipped into a
 *    page never runs; fonts from us alone (self-hosted); plugins never;
 *    forms post only to us. Map tiles are the one outside image source
 *    (OpenStreetMap, decision D18).
 *
 * Known limit: some screens size things with style="" attributes, so styles
 * keep 'unsafe-inline'. Scripts do not.
 */
class SecurityHeaders
{
    private const CSP = [
        "default-src 'self'",
        "script-src 'self' 'nonce-{nonce}'",
        "style-src 'self' 'unsafe-inline'",
        "font-src 'self' data:",
        "img-src 'self' data: blob: https://tile.openstreetmap.org",
        "media-src 'self' blob:",
        "connect-src 'self'",
        "worker-src 'self'",
        "manifest-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // One nonce per response; @vite stamps it on the tags it writes.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(self), geolocation=(), microphone=(), payment=(), usb=(), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', str_replace('{nonce}', $nonce, implode('; ', self::CSP)));
        }

        // Only over https: "always https" on plain http would lock a laptop
        // test out of its own server.
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // The app and the console are not for search engines.
        if (! $request->routeIs('site.*')) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // Do not advertise the PHP version.
        $headers->remove('X-Powered-By');
        if (function_exists('header_remove') && ! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
