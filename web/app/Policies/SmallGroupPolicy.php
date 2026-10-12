<?php

namespace App\Policies;

use App\Models\SmallGroup;
use App\Models\User;

class SmallGroupPolicy
{
    public function view(?User $user, SmallGroup $group): bool
    {
        return $user !== null && ($user->isMemberOf($group->church_id) || $user->canInChurch($group->church_id, 'groups.manage'));
    }

    /** Group leaders can take attendance and post group announcements for their own group. */
    public function lead(User $user, SmallGroup $group): bool
    {
        return $user->canInChurch($group->church_id, 'groups.manage') || $group->isLedBy($user);
    }

    public function manage(User $user, SmallGroup $group): bool
    {
        return $user->canInChurch($group->church_id, 'groups.manage');
    }
}
