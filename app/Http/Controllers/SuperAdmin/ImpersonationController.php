<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets Kakebe support staff see exactly what a church admin sees.
 * Start and stop are both written to the audit log.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin->isSuperAdmin(), 403);
        abort_if($user->isSuperAdmin() || $user->is($admin), 403, __('You cannot impersonate this account.'));

        Audit::log('impersonation.started', $user, __(':admin impersonated :user', ['admin' => $admin->name, 'user' => $user->name]));

        $request->session()->put('impersonator_id', $admin->id);
        Auth::guard('web')->login($user);

        return redirect($user->homeUrl())->with('info', __('You are now viewing FaithFellows as :name.', ['name' => $user->name]));
    }

    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('impersonator_id');
        abort_unless($adminId, 403);

        $impersonated = $request->user();
        $admin = User::findOrFail($adminId);
        Auth::guard('web')->login($admin);

        Audit::log('impersonation.stopped', $impersonated, __(':admin stopped impersonating', ['admin' => $admin->name]));

        return redirect()->route('super-admin.dashboard')->with('success', __('Welcome back, :name.', ['name' => $admin->first_name]));
    }
}
