<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        return view('super-admin.users.index', [
            'users' => $this->query($request)->with(['media', 'roles'])->withCount(['subscriptions' => fn ($q) => $q->where('status', 'active')])
                ->latest()->paginate(30)->withQueryString(),
            'counts' => [
                'all' => User::count(),
                'suspended' => User::where('status', 'suspended')->count(),
                'unverified' => User::whereNull('email_verified_at')->count(),
                'admins' => User::role(['super_admin', 'church_admin'])->count(),
            ],
        ]);
    }

    public function show(User $user): View
    {
        return view('super-admin.users.show', [
            'user' => $user->load(['media', 'roles']),
            'subscriptions' => $user->subscriptions()->with('church')->latest('subscribed_at')->get(),
            'staff' => $user->staffMemberships()->with(['church', 'role'])->get(),
            'giving' => DB::table('donations')->where('user_id', $user->id)->where('status', 'succeeded')->selectRaw('COUNT(*) n, COALESCE(SUM(amount), 0) total')->first(),
            'activity' => AuditLog::where('user_id', $user->id)->latest('created_at')->take(15)->get(),
            'devices' => $user->pushSubscriptions()->count(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['suspend', 'reactivate', 'verify', 'reset_2fa'])]]);
        abort_if($user->is($request->user()), 422, __('You cannot change your own account here.'));
        abort_if($user->isSuperAdmin() && $data['action'] === 'suspend', 422, __('Remove the super admin role before suspending.'));

        match ($data['action']) {
            'suspend' => $user->update(['status' => 'suspended']),
            'reactivate' => $user->update(['status' => 'active']),
            'verify' => $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save(),
            'reset_2fa' => $user->forceFill(['two_factor_enabled' => false, 'two_factor_code' => null])->save(),
        };

        if ($data['action'] === 'suspend') {
            // End every session so the suspension takes effect immediately.
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        Audit::log('user.'.$data['action'], $user);

        return back()->with('success', __('User updated.'));
    }

    public function export(Request $request): StreamedResponse
    {
        return CsvExport::download('users-'.now()->format('Ymd').'.csv', ['ID', 'Name', 'Email', 'Phone', 'Country', 'City', 'Status', 'Verified', 'Joined', 'Last login'],
            $this->query($request)->orderBy('id')->lazy()->map(fn (User $u) => [
                $u->id, $u->name, $u->email, $u->phone, $u->country, $u->city, $u->status,
                $u->email_verified_at ? 'yes' : 'no', $u->created_at?->toDateString(), $u->last_login_at?->toDateTimeString(),
            ]));
    }

    protected function query(Request $request)
    {
        return User::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->query('q').'%')
                ->orWhere('email', 'like', '%'.$request->query('q').'%')
                ->orWhere('phone', 'like', '%'.$request->query('q').'%')))
            ->when($request->query('filter') === 'suspended', fn ($q) => $q->where('status', 'suspended'))
            ->when($request->query('filter') === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($request->query('filter') === 'admins', fn ($q) => $q->role(['super_admin', 'church_admin']));
    }
}
