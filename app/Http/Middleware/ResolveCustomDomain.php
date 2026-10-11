<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ChurchSite\SiteController;
use App\Models\Church;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requests for a verified custom domain (www.gracechurch.org) render that
 * church's website. Only the home page is served there; other links go to
 * the main platform.
 */
class ResolveCustomDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $root = strtolower(config('faithfellows.root_domain'));
        $appHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

        $isPlatformHost = $host === $root || $host === $appHost || str_ends_with($host, '.'.$root)
            || in_array($host, ['localhost', '127.0.0.1'], true);

        if (! $isPlatformHost && $request->path() === '/') {
            $church = Church::where('custom_domain', preg_replace('/^www\./', '', $host))
                ->where('custom_domain_verified', true)->first();

            if ($church) {
                $request->attributes->set('siteChurch', $church);

                return response(app()->call([app(SiteController::class), 'show']));
            }
        }

        return $next($request);
    }
}
