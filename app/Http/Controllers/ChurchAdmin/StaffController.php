<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Church\StaffRequest;
use App\Models\Church;
use App\Models\ChurchRole;
use App\Models\ChurchStaff;
use App\Services\ChurchOnboardingService;
use Illuminate\Http\RedirectResponse;

class StaffController extends Controller
{
    public function __construct(protected ChurchOnboardingService $onboarding) {}

    public function store(StaffRequest $request, Church $church): RedirectResponse
    {
        $isAdmin = $request->input('access') === 'admin';
        $this->onboarding->addStaff(
            $church,
            $request->input('name'),
            $request->input('email'),
            $isAdmin ? null : ChurchRole::find($request->input('church_role_id')),
            $isAdmin,
            $request->input('branch_id'),
            $request->input('title'),
        );

        return back()->with('success', __(':name has been added and notified by email.', ['name' => $request->input('name')]));
    }

    public function update(StaffRequest $request, Church $church, ChurchStaff $staffMember): RedirectResponse
    {
        abort_unless($staffMember->church_id === $church->id, 404);
        $isAdmin = $request->input('access') === 'admin';
        $this->guardLastAdmin($church, $staffMember, $isAdmin);

        $staffMember->update([
            'is_admin' => $isAdmin,
            'church_role_id' => $isAdmin ? null : $request->input('church_role_id'),
            'branch_id' => $request->input('branch_id'),
            'title' => $request->input('title'),
        ]);
        if ($isAdmin) {
            $staffMember->user->assignRole('church_admin');
        }

        return back()->with('success', __('Staff access updated.'));
    }

    public function destroy(Church $church, ChurchStaff $staffMember): RedirectResponse
    {
        $this->authorize('manageStaff', $church);
        abort_unless($staffMember->church_id === $church->id, 404);
        $this->guardLastAdmin($church, $staffMember, false);

        $this->onboarding->removeStaff($staffMember);

        return back()->with('success', __('Staff member removed.'));
    }

    /**
     * A church must always keep at least one admin.
     */
    protected function guardLastAdmin(Church $church, ChurchStaff $staff, bool $willBeAdmin): void
    {
        if ($staff->is_admin && ! $willBeAdmin) {
            $admins = $church->staffMembers()->where('is_admin', true)->where('status', 'active')->count();
            abort_if($admins <= 1, 422, __('A church must have at least one admin.'));
        }
    }
}
