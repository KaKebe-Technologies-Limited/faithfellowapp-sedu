<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Guards /api/v1/platform/*: only the platform's own admins (super admins) run the whole of FaithFellows from the app. */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, __('Only the platform admin can do this.'));

        return $next($request);
    }
}
