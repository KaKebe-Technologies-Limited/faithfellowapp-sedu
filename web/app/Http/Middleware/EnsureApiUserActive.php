<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API counterpart of EnsureAccountActive: a suspended account's token is
 * revoked on its next request.
 */
class EnsureApiUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => __('This account is suspended.')], 403);
        }

        return $next($request);
    }
}
