<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AnnouncementPolicy extends ChurchContentPolicy
{
    protected string $permission = 'content.manage';

    /**
     * Announcements use an audience instead of a visibility flag.
     */
    public function view(?User $user, Model $model): bool
    {
        if ($user && $user->canInChurch($model->church_id, $this->permission)) {
            return true;
        }

        if (! $model->church?->isActive() || ! $model->isPublished()) {
            return false;
        }

        return match ($model->audience) {
            'public' => true,
            'members', 'branch' => $user?->isMemberOf($model->church_id) ?? false,
            'staff' => $user?->isStaffOf($model->church_id) ?? false,
            'group' => $user !== null && (
                $user->isStaffOf($model->church_id)
                || \DB::table('small_group_members')->where('small_group_id', $model->small_group_id)->where('user_id', $user->id)->exists()
            ),
            default => false,
        };
    }
}
