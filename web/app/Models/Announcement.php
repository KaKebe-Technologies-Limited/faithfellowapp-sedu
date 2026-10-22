<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\HasEngagement;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Announcement extends Model implements HasMedia
{
    use Auditable, BelongsToChurch, HasEngagement, InteractsWithMedia, Publishable, SoftDeletes;

    protected $fillable = [
        'church_id', 'branch_id', 'small_group_id', 'user_id', 'title', 'body', 'audience', 'priority',
        'status', 'channels', 'published_at', 'expires_at', 'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        $this->addMediaConversion('feed')->width(1080)->nonQueued();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->published()->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Announcements a user may see. Group/staff audiences are resolved by the caller
     * passing the user's group ids / staff church ids.
     *
     * @param  array<int, int>  $memberChurchIds
     * @param  array<int, int>  $staffChurchIds
     * @param  array<int, int>  $groupIds
     */
    public function scopeForAudience(Builder $query, array $memberChurchIds, array $staffChurchIds = [], array $groupIds = []): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('audience', 'public')
            ->orWhere(fn ($m) => $m->whereIn('audience', ['members', 'branch'])->whereIn('church_id', $memberChurchIds))
            ->orWhere(fn ($s) => $s->where('audience', 'staff')->whereIn('church_id', $staffChurchIds))
            ->orWhere(fn ($g) => $g->where('audience', 'group')->whereIn('small_group_id', $groupIds)));
    }

    public function getImageUrlAttribute(): ?string
    {
        $media = $this->relationLoaded('media') ? $this->media->firstWhere('collection_name', 'image') : $this->getFirstMedia('image');

        return $media?->getUrl('feed');
    }
}
