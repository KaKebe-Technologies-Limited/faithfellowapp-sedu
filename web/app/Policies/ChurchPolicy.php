<?php

namespace App\Policies;

use App\Models\Church;
use App\Models\User;

/**
 * Super admins are allowed everything via Gate::before.
 */
class ChurchPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /** Public pages: active churches for everyone, any status for its staff. */
    public function view(?User $user, Church $church): bool
    {
        return $church->isActive() || ($user && $user->isStaffOf($church));
    }

    public function manage(User $user, Church $church): bool
    {
        return $user->isStaffOf($church);
    }

    public function update(User $user, Church $church): bool
    {
        return $user->canInChurch($church, 'settings.manage');
    }

    public function manageStaff(User $user, Church $church): bool
    {
        return $user->canInChurch($church, 'roles.manage');
    }

    /** Lifecycle transitions belong to Kakebe staff only. */
    public function approve(User $user, Church $church): bool
    {
        return false;
    }

    public function delete(User $user, Church $church): bool
    {
        return false;
    }
}
