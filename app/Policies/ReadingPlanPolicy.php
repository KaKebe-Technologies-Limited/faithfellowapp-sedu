<?php

namespace App\Policies;

use Illuminate\Database\Eloquent\Model;

class ReadingPlanPolicy extends ChurchContentPolicy
{
    protected string $permission = 'content.manage';

    protected function isPublished(Model $model): bool
    {
        return (bool) $model->is_published;
    }
}
