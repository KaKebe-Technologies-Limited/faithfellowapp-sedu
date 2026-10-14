<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\HasArtwork;
use App\Models\Concerns\HasChurchSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Devotional extends Model implements HasMedia
{
    use Auditable, BelongsToChurch, HasArtwork, HasChurchSlug, HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'church_id', 'user_id', 'title', 'slug', 'scripture', 'scripture_text', 'body', 'prayer',
        'audio_url', 'publish_date', 'status', 'visibility', 'notified_at',
    ];

    public function artworkKind(): string
    {
        return 'devotional';
    }

    public function uploadedPicture(): ?string
    {
        return null;
    }

    protected function casts(): array
    {
        return ['publish_date' => 'date', 'notified_at' => 'datetime'];
    }

    public function registerMediaCollections(): void
    {
        // Audio devotionals are data-light (~1 MB per 10 minutes at 16 kbps).
        $this->addMediaCollection('audio')->singleFile()->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/ogg', 'audio/webm']);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereDate('publish_date', '<=', today());
    }

    /**
     * @param  array<int, int>  $memberChurchIds
     */
    public function scopeVisibleTo(Builder $query, array $memberChurchIds): Builder
    {
        return $query->where(fn ($q) => $q->where('visibility', 'public')->orWhereIn('church_id', $memberChurchIds));
    }

    public function getAudioSrcAttribute(): ?string
    {
        $media = $this->relationLoaded('media') ? $this->media->firstWhere('collection_name', 'audio') : $this->getFirstMedia('audio');

        return $media?->getUrl() ?: $this->audio_url;
    }
}
