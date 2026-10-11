<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\HasChurchSlug;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Sermon extends Model implements HasMedia
{
    use Auditable, BelongsToChurch, HasChurchSlug, HasFactory, InteractsWithMedia, Publishable, SoftDeletes;

    protected $fillable = [
        'church_id', 'branch_id', 'sermon_series_id', 'user_id', 'title', 'slug', 'preacher', 'scripture', 'description',
        'audio_url', 'video_url', 'duration_seconds', 'language', 'status', 'visibility', 'published_at', 'notified_at', 'preached_on',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'notified_at' => 'datetime',
            'preached_on' => 'date',
            'duration_seconds' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('audio')->singleFile()
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/ogg', 'audio/wav', 'audio/webm']);
        $this->addMediaCollection('video')->singleFile()->acceptsMimeTypes(['video/mp4', 'video/webm']);
        $this->addMediaCollection('notes')->singleFile()->acceptsMimeTypes(['application/pdf']);
        $this->addMediaCollection('thumbnail')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        $this->addMediaConversion('card')->width(640)->height(360)->nonQueued()->performOnCollections('thumbnail');
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function series(): BelongsTo
    {
        return $this->belongsTo(SermonSeries::class, 'sermon_series_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(SermonTranscript::class);
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class)->withPivot('position');
    }

    public function views(): MorphMany
    {
        return $this->morphMany(ContentView::class, 'viewable');
    }

    // ── Media helpers (use eager-loaded media when available) ────────────

    protected function mediaIn(string $collection): ?SpatieMedia
    {
        return $this->relationLoaded('media')
            ? $this->media->firstWhere('collection_name', $collection)
            : $this->getFirstMedia($collection);
    }

    public function getAudioSrcAttribute(): ?string
    {
        return $this->mediaIn('audio')?->getUrl() ?: $this->audio_url;
    }

    public function getVideoFileSrcAttribute(): ?string
    {
        return $this->mediaIn('video')?->getUrl();
    }

    public function getNotesUrlAttribute(): ?string
    {
        return $this->mediaIn('notes')?->getUrl();
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->mediaIn('thumbnail')?->getUrl('card');
    }

    /**
     * Embeddable URL for YouTube / Facebook links, or null for direct files.
     */
    public function getVideoEmbedUrlAttribute(): ?string
    {
        $url = (string) $this->video_url;
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|live/|embed/)|youtu\.be/)([\w-]{11})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }
        if (str_contains($url, 'facebook.com')) {
            return 'https://www.facebook.com/plugins/video.php?href='.urlencode($url).'&show_text=false';
        }

        return null;
    }

    public function getDurationLabelAttribute(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }
        $m = intdiv($this->duration_seconds, 60);

        return $m >= 60 ? intdiv($m, 60).'h '.($m % 60).'m' : $m.' min';
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
            ->orWhere('preacher', 'like', "%{$term}%")
            ->orWhere('scripture', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%")
            ->orWhereHas('transcripts', fn ($t) => $t->where('body', 'like', "%{$term}%")));
    }
}
