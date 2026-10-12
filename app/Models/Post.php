<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\HasEngagement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Post extends Model implements HasMedia
{
    use Auditable, BelongsToChurch, HasEngagement, HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = ['church_id', 'branch_id', 'user_id', 'body', 'visibility', 'status', 'is_pinned', 'published_at', 'notified_at'];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'published_at' => 'datetime', 'notified_at' => 'datetime'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        $this->addMediaConversion('feed')->width(1080)->nonQueued();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }

    /**
     * Posts a viewer may see: public ones, plus members-only for subscribers/staff.
     *
     * @param  array<int, int>  $memberChurchIds
     */
    public function scopeVisibleTo(Builder $query, array $memberChurchIds): Builder
    {
        return $query->where(fn ($q) => $q->where('visibility', 'public')
            ->orWhereIn('church_id', $memberChurchIds));
    }

    public function auditLabel(): ?string
    {
        return Str::limit(strip_tags($this->body), 60);
    }
}
