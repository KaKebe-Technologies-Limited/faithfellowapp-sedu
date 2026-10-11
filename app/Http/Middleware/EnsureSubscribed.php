<?php

namespace App\Http\Middleware;

use App\Models\Church;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware 'subscribed': the user must follow (or staff) the
 * {church} in the route to see members-only pages.
 */
class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $church = $request->route('church');
        if (! $church instanceof Church) {
            $church = Church::where('slug', $church)->firstOrFail();
        }

        $user = $request->user();
        if (! $user || ! ($user->isMemberOf($church) || $user->isSuperAdmin())) {
            return redirect()->route('churches.show', $church)
                ->with('info', __('Subscribe to :church to see this page.', ['church' => $church->name]));
        }

        return $next($request);
    }
}
