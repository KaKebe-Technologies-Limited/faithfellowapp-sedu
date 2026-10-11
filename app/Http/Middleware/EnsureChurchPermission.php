<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('church.can:giving.view')
 * Several permissions separated by "|" means any of them.
 */
class EnsureChurchPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $church = app()->bound('currentChurch') ? app('currentChurch') : $request->route('church');
        $user = $request->user();

        $allowed = collect(explode('|', $permissions))->contains(fn ($p) => $user?->canInChurch($church, $p));
        abort_unless($allowed, 403, __('Your role does not allow this action.'));

        return $next($request);
    }
}
