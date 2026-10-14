<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers on every response.
 *
 * No Content-Security-Policy yet: pages embed YouTube / Cloudflare Stream /
 * Facebook players, OpenStreetMap tiles, Stripe Checkout and Google Fonts,
 * and churches paste embed codes — a strict CSP needs a per-church allow-list
 * first (see DECISIONS.md).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Geolocation for service check-in; camera for QR ticket scanning; nothing else.
        $headers->set('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=(), payment=(self), usb=(), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        // The podcast player is meant to be embedded on other websites; every other page may only be framed by this site.
        if (! $headers->has('X-Frame-Options') && ! $request->routeIs('podcasts.embed')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        if ($request->is('church/*', 'super-admin*', 'profile*', 'api/*')) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
