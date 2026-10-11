<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the user's language preference (or ?lang= / session for guests)
 * and shares the low-bandwidth "lite" flag with every view.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('faithfellows.languages'));

        if ($request->filled('lang') && in_array($request->query('lang'), $supported, true)) {
            $request->session()->put('locale', $request->query('lang'));
        }

        $locale = $request->user()?->language ?? $request->session()->get('locale', config('app.locale'));
        app()->setLocale(in_array($locale, $supported, true) ? $locale : 'en');

        $lite = $request->cookie('ff_lite') === '1' || (bool) $request->user()?->low_bandwidth;
        view()->share('ffLite', $lite);

        return $next($request);
    }
}
