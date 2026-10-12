<?php

namespace App\Policies;

use App\Models\ChurchRole;
use App\Models\User;

class ChurchRolePolicy
{
    public function update(User $user, ChurchRole $role): bool
    {
        return $user->canInChurch($role->church_id, 'roles.manage');
    }

    public function delete(User $user, ChurchRole $role): bool
    {
        return $user->canInChurch($role->church_id, 'roles.manage');
    }
}
