<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared rules for church content (sermons, devotionals, announcements…).
 *
 *  - view:   published + (public, or viewer is a member/staff); staff see drafts.
 *  - manage: staff holding the module's permission in that church.
 */
abstract class ChurchContentPolicy
{
    protected string $permission = 'content.manage';

    public function view(?User $user, Model $model): bool
    {
        $church = $model->church;

        if ($user && $user->canInChurch($model->church_id, $this->permission)) {
            return true;
        }

        if (! $church?->isActive() || ! $this->isPublished($model)) {
            return false;
        }

        return ($model->visibility ?? 'public') === 'public'
            || ($user !== null && $user->isMemberOf($model->church_id));
    }

    public function create(User $user, Model|string|null $churchOrModel = null): bool
    {
        return true; // creation is gated by route middleware church.can:<permission>
    }

    public function update(User $user, Model $model): bool
    {
        return $user->canInChurch($model->church_id, $this->permission);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }

    protected function isPublished(Model $model): bool
    {
        return method_exists($model, 'isPublished') ? $model->isPublished() : true;
    }
}
