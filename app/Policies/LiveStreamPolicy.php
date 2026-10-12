<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Live streams are visible to everyone once live/ended (they're services
 * people are invited to); scheduled ones show a countdown.
 */
class LiveStreamPolicy extends ChurchContentPolicy
{
    protected string $permission = 'live.manage';

    public function view(?User $user, Model $model): bool
    {
        if ($user && $user->canInChurch($model->church_id, $this->permission)) {
            return true;
        }

        return (bool) $model->church?->isActive();
    }
}
