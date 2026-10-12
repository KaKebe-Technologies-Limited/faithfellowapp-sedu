<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Church\ChurchRoleRequest;
use App\Models\Church;
use App\Models\ChurchRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Staff & roles: list staff, invite people, and edit sub-roles with a
 * permission matrix (Editor, Finance, Media, Events, Moderator, Custom).
 */
class RoleController extends Controller
{
    public function index(Church $church): View
    {
        $this->authorize('manageStaff', $church);

        return view('church-admin.roles.index', [
            'church' => $church,
            'roles' => $church->roles()->withCount('staff')->orderByDesc('is_preset')->orderBy('name')->get(),
            'staff' => $church->staffMembers()->with(['user.media', 'role', 'branch'])->where('status', '!=', 'removed')
                ->orderByDesc('is_admin')->paginate(25),
            'branches' => $church->branches()->orderBy('name')->pluck('name', 'id'),
            'catalog' => config('faithfellows.church_permissions'),
        ]);
    }

    public function create(Church $church): View
    {
        $this->authorize('manageStaff', $church);

        return view('church-admin.roles.form', [
            'church' => $church,
            'role' => new ChurchRole(['permissions' => []]),
            'catalog' => config('faithfellows.church_permissions'),
            'presets' => config('faithfellows.role_presets'),
        ]);
    }

    public function store(ChurchRoleRequest $request, Church $church): RedirectResponse
    {
        $base = Str::slug($request->input('name')) ?: 'role';
        $slug = $base;
        for ($i = 2; $church->roles()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        $church->roles()->create([
            'name' => $request->input('name'),
            'slug' => $slug,
            'description' => $request->input('description'),
            'permissions' => array_values($request->input('permissions', [])),
        ]);

        return redirect()->route('church-admin.roles.index', $church)->with('success', __('Role created.'));
    }

    public function edit(Church $church, ChurchRole $role): View
    {
        $this->authorize('update', $role);

        return view('church-admin.roles.form', [
            'church' => $church,
            'role' => $role,
            'catalog' => config('faithfellows.church_permissions'),
            'presets' => config('faithfellows.role_presets'),
        ]);
    }

    public function update(ChurchRoleRequest $request, Church $church, ChurchRole $role): RedirectResponse
    {
        $role->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'permissions' => array_values($request->input('permissions', [])),
        ]);

        return redirect()->route('church-admin.roles.index', $church)->with('success', __('Role updated.'));
    }

    public function destroy(Church $church, ChurchRole $role): RedirectResponse
    {
        $this->authorize('delete', $role);
        abort_if($role->staff()->exists(), 422, __('Reassign staff before deleting this role.'));
        $role->delete();

        return back()->with('success', __('Role deleted.'));
    }
}
