<?php

namespace App\Services;

use App\Models\Church;
use App\Models\ChurchRole;
use App\Models\ChurchStaff;
use App\Models\User;
use App\Notifications\Church\ChurchApplicationReceived;
use App\Notifications\Church\ChurchStatusChanged;
use App\Notifications\Church\StaffInvitation;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Church lifecycle: apply / invite → approve → suspend → archive.
 */
class ChurchOnboardingService
{
    /**
     * A signed-in user applies to register a church (status: pending).
     *
     * @param  array<string, mixed>  $data
     */
    public function apply(User $applicant, array $data): Church
    {
        $church = DB::transaction(function () use ($applicant, $data) {
            $church = Church::create([
                ...$data,
                'slug' => Church::uniqueSlug($data['slug'] ?? $data['name']),
                'owner_id' => $applicant->id,
                'status' => 'pending',
                'website_blocks' => Church::DEFAULT_BLOCKS,
            ]);

            $this->attachAdmin($church, $applicant);

            return $church;
        });

        Audit::log('church.applied', $church, $church->name);
        Notification::send(User::role('super_admin')->get(), new ChurchApplicationReceived($church));

        return $church;
    }

    /**
     * Kakebe staff onboard a church directly and invite its admin by email.
     *
     * @param  array<string, mixed>  $data
     */
    public function invite(array $data, string $adminName, string $adminEmail, bool $activate = true): Church
    {
        return DB::transaction(function () use ($data, $adminName, $adminEmail, $activate) {
            [$admin, $isNew] = $this->findOrCreateUser($adminName, $adminEmail);

            $church = Church::create([
                ...$data,
                'slug' => Church::uniqueSlug($data['slug'] ?? $data['name']),
                'owner_id' => $admin->id,
                'status' => 'pending',
                'website_blocks' => Church::DEFAULT_BLOCKS,
            ]);

            $this->attachAdmin($church, $admin);
            Audit::log('church.invited', $church, $church->name, [], ['admin_email' => $adminEmail]);

            if ($activate) {
                $this->approve($church, notify: false);
            }

            $admin->notify(new StaffInvitation($church, __('Church admin'), $isNew ? Password::createToken($admin) : null));

            return $church;
        });
    }

    public function approve(Church $church, bool $notify = true): Church
    {
        DB::transaction(function () use ($church) {
            $church->update([
                'status' => 'active',
                'approved_at' => $church->approved_at ?? now(),
                'approved_by' => auth()->id(),
                'status_reason' => null,
            ]);

            $this->createPresetRoles($church);
            $this->ensureMainBranch($church);
        });

        Audit::log('church.approved', $church, $church->name);

        if ($notify && $church->owner) {
            $church->owner->notify(new ChurchStatusChanged($church, 'approved'));
        }

        return $church;
    }

    public function suspend(Church $church, ?string $reason = null): Church
    {
        $church->update(['status' => 'suspended', 'status_reason' => $reason]);
        Audit::log('church.suspended', $church, $reason);
        $church->owner?->notify(new ChurchStatusChanged($church, 'suspended', $reason));

        return $church;
    }

    public function reactivate(Church $church): Church
    {
        $church->update(['status' => 'active', 'status_reason' => null]);
        Audit::log('church.reactivated', $church, $church->name);
        $church->owner?->notify(new ChurchStatusChanged($church, 'reactivated'));

        return $church;
    }

    public function archive(Church $church, ?string $reason = null): Church
    {
        $church->update(['status' => 'archived', 'status_reason' => $reason]);
        Audit::log('church.archived', $church, $reason);

        return $church;
    }

    /**
     * Add (or update) a staff member by email. Creates the account when needed.
     */
    public function addStaff(Church $church, string $name, string $email, ?ChurchRole $role, bool $isAdmin = false, ?int $branchId = null, ?string $title = null): ChurchStaff
    {
        return DB::transaction(function () use ($church, $name, $email, $role, $isAdmin, $branchId, $title) {
            [$user, $isNew] = $this->findOrCreateUser($name, $email);

            $staff = ChurchStaff::updateOrCreate(
                ['church_id' => $church->id, 'user_id' => $user->id],
                [
                    'church_role_id' => $isAdmin ? null : $role?->id,
                    'is_admin' => $isAdmin,
                    'branch_id' => $branchId,
                    'title' => $title,
                    'status' => 'active',
                    'invited_by' => auth()->id(),
                ],
            );

            $user->assignRole(Role::findOrCreate($isAdmin ? 'church_admin' : 'staff', 'web'));
            $user->flushChurchPermissionCache();

            $user->notify(new StaffInvitation($church, $isAdmin ? __('Church admin') : ($role?->name ?? __('Staff')), $isNew ? Password::createToken($user) : null));

            return $staff;
        });
    }

    public function removeStaff(ChurchStaff $staff): void
    {
        $user = $staff->user;
        $staff->delete();

        // Drop the platform role when the user no longer staffs any church.
        if ($user && ! $user->staffMemberships()->exists()) {
            $user->removeRole('church_admin');
            $user->removeRole('staff');
        }
    }

    public function createPresetRoles(Church $church): void
    {
        foreach (config('faithfellows.role_presets') as $name => $permissions) {
            ChurchRole::firstOrCreate(
                ['church_id' => $church->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'permissions' => $permissions, 'is_preset' => true],
            );
        }
    }

    protected function ensureMainBranch(Church $church): void
    {
        if (! $church->branches()->exists()) {
            $church->branches()->create([
                'name' => __('Main campus'),
                'slug' => 'main',
                'city' => $church->city,
                'address' => $church->address,
                'lat' => $church->lat,
                'lng' => $church->lng,
                'phone' => $church->phone,
                'is_main' => true,
            ]);
        }
    }

    protected function attachAdmin(Church $church, User $user): void
    {
        ChurchStaff::updateOrCreate(
            ['church_id' => $church->id, 'user_id' => $user->id],
            ['is_admin' => true, 'status' => 'active', 'title' => __('Church admin')],
        );
        $user->assignRole(Role::findOrCreate('church_admin', 'web'));
    }

    /**
     * @return array{0: User, 1: bool}
     */
    protected function findOrCreateUser(string $name, string $email): array
    {
        $email = Str::lower(trim($email));
        $user = User::where('email', $email)->first();
        if ($user) {
            return [$user, false];
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Str::password(32),
            'terms_accepted_at' => null,
        ]);
        $user->assignRole(Role::findOrCreate('member', 'web'));
        // Invited by email — the invitation link proves ownership of the address.
        $user->forceFill(['email_verified_at' => now()])->save();

        return [$user, true];
    }
}
