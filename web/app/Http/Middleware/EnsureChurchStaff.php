<?php

namespace App\Http\Middleware;

use App\Models\Church;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /church/{church}/admin/*: the user must be active staff of this
 * church (or a super admin). Binds the church as the current tenant.
 */
class EnsureChurchStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $church = $request->route('church');
        if (! $church instanceof Church) {
            $church = Church::where('slug', $church)->firstOrFail();
        }

        $user = $request->user();
        abort_unless($user && $user->isStaffOf($church), 403, __('You are not a staff member of this church.'));
        abort_if($church->status === 'archived' && ! $user->isSuperAdmin(), 403, __('This church has been archived.'));

        app()->instance('currentChurch', $church);
        view()->share('currentChurch', $church);

        return $next($request);
    }
}
