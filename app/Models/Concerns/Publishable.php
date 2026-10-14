<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Draft / scheduled / published content with public or members-only visibility.
 */
trait Publishable
{
    /** Live now: published, or scheduled and its time has come. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('status'), ['published', 'scheduled'])
            ->whereNotNull($this->qualifyColumn('published_at'))
            ->where($this->qualifyColumn('published_at'), '<=', now());
    }

    /**
     * Public items, plus members-only items for churches the viewer belongs to.
     *
     * @param  array<int, int>  $memberChurchIds
     */
    public function scopeVisibleTo(Builder $query, array $memberChurchIds): Builder
    {
        return $query->where(fn ($q) => $q->where($this->qualifyColumn('visibility'), 'public')
            ->orWhereIn($this->qualifyColumn('church_id'), $memberChurchIds));
    }

    public function isPublished(): bool
    {
        return in_array($this->status, ['published', 'scheduled'], true)
            && $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * Normalise status/published_at from a form: scheduled when in the future.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function resolvePublishing(array $data): array
    {
        $status = $data['status'] ?? 'published';
        if ($status === 'draft') {
            return [...$data, 'status' => 'draft'];
        }

        $at = ! empty($data['published_at']) ? Carbon::parse($data['published_at']) : now();

        return [...$data, 'published_at' => $at, 'status' => $at->isFuture() ? 'scheduled' : 'published'];
    }
}
