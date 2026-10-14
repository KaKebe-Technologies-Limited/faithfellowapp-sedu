<?php

namespace App\Http\Middleware;

use App\Support\AppFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a platform feature (podcasts, hymns or radio) away from people whose churches the super admin switched it off for:
 * `feature:podcasts`. Someone with no church yet always gets through (see AppFeatures).
 */
class EnsureAppFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! AppFeatures::allows($request->user(), $feature)) {
            $label = AppFeatures::FEATURES[$feature]['label'] ?? $feature;

            return response()->json(['message' => __(':feature is not switched on for your church yet.', ['feature' => __($label)]), 'feature' => $feature], 403);
        }

        return $next($request);
    }
}
