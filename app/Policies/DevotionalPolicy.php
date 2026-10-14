<?php

namespace App\Policies;

use Illuminate\Database\Eloquent\Model;

class DevotionalPolicy extends ChurchContentPolicy
{
    protected string $permission = 'content.manage';

    protected function isPublished(Model $model): bool
    {
        return $model->status === 'published' && $model->publish_date?->lte(today());
    }
}
