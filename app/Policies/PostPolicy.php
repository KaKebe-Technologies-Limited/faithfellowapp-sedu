<?php

namespace App\Policies;

use Illuminate\Database\Eloquent\Model;

class PostPolicy extends ChurchContentPolicy
{
    protected string $permission = 'community.moderate';

    protected function isPublished(Model $model): bool
    {
        return $model->status === 'published' && $model->published_at?->isPast();
    }
}
