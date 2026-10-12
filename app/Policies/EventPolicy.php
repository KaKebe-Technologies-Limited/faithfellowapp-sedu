<?php

namespace App\Policies;

use Illuminate\Database\Eloquent\Model;

class EventPolicy extends ChurchContentPolicy
{
    protected string $permission = 'events.manage';

    protected function isPublished(Model $model): bool
    {
        return $model->status === 'published';
    }
}
