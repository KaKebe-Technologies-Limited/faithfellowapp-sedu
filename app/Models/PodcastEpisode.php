<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One episode: a title, a few words about it, and the link to its audio on the host site. */
class PodcastEpisode extends Model
{
    use SoftDeletes;

    protected $fillable = ['podcast_id', 'guid', 'title', 'summary', 'audio_url', 'duration_seconds', 'season', 'number', 'status', 'published_at', 'plays_count'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'duration_seconds' => 'integer', 'plays_count' => 'integer'];
    }

    protected static function booted(): void
    {
        $refresh = fn (PodcastEpisode $e) => $e->podcast?->refreshCounts();
        static::saved($refresh);
        static::deleted($refresh);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(PodcastComment::class, 'commentable');
    }

    public function podcast(): BelongsTo
    {
        return $this->belongsTo(Podcast::class)->withTrashed();
    }

    /** Published and already out (not scheduled for later). */
    public function scopeOut(Builder $query): Builder
    {
        return $query->where('status', 'published')->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /** "24:10" or "1:02:05"; null when the length is not known. */
    public function durationLabel(): ?string
    {
        $s = $this->duration_seconds;

        return $s ? ($s >= 3600 ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60) : sprintf('%d:%02d', intdiv($s, 60), $s % 60)) : null;
    }
}
