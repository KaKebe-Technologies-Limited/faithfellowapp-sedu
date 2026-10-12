<?php

namespace App\Models\Concerns;

use App\Models\Church;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-scoped model: every row belongs to exactly one church.
 *
 * Isolation is enforced by (1) route scoped bindings under /church/{church}/admin,
 * (2) policies checking $model->church_id against the user's staff memberships,
 * and (3) the forChurch() scope on every admin query.
 */
trait BelongsToChurch
{
    public static function bootBelongsToChurch(): void
    {
        static::creating(function ($model) {
            if (empty($model->church_id) && app()->bound('currentChurch') && app('currentChurch')) {
                $model->church_id = app('currentChurch')->id;
            }
        });
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function scopeForChurch(Builder $query, Church|int $church): Builder
    {
        return $query->where($this->qualifyColumn('church_id'), $church instanceof Church ? $church->id : $church);
    }

    /**
     * @param  iterable<int>  $churchIds
     */
    public function scopeForChurches(Builder $query, iterable $churchIds): Builder
    {
        return $query->whereIn($this->qualifyColumn('church_id'), $churchIds);
    }
}
