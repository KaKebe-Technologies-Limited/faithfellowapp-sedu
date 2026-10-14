<?php

namespace App\Policies;

use Illuminate\Database\Eloquent\Model;

class CampaignPolicy extends ChurchContentPolicy
{
    protected string $permission = 'campaigns.manage';

    protected function isPublished(Model $model): bool
    {
        return in_array($model->status, ['active', 'completed'], true);
    }
}
