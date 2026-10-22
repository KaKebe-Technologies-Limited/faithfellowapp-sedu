<?php

namespace App\Models;

use App\Support\ContentArtwork;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

/**
 * A faith-based podcast made by a member. Listeners find it in the app once the super admin has approved it; the
 * audio of each episode stays on the host site and is played from its link.
 */
class Podcast extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    /** key => label */
    public const CATEGORIES = [
        'faith' => 'Faith', 'motivation' => 'Motivation', 'teaching' => 'Bible teaching', 'sermons' => 'Sermons', 'devotional' => 'Devotionals', 'prayer' => 'Prayer',
        'worship' => 'Worship & music', 'testimonies' => 'Testimonies', 'family' => 'Marriage & family', 'youth' => 'Youth',
        'kids' => 'Kids', 'leadership' => 'Leadership', 'missions' => 'Missions & outreach', 'interviews' => 'Interviews',
    ];

    protected $fillable = ['user_id', 'name', 'slug', 'host_name', 'summary', 'description', 'category', 'language', 'website_url', 'feed_url', 'feed_synced_at', 'feed_error', 'status', 'review_note', 'approved_at', 'episodes_count', 'followers_count', 'latest_episode_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'latest_episode_at' => 'datetime', 'feed_synced_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Podcast $podcast) {
            $podcast->slug = $podcast->slug ?: static::uniqueSlug($podcast->name);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'podcast';
        $slug = $base;
        for ($i = 2; static::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        $this->addMediaConversion('card')->width(700)->height(700)->nonQueued();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(PodcastEpisode::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'podcast_follows')->withTimestamps();
    }

    /** What listeners can see: approved podcasts. */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function isListed(): bool
    {
        return $this->status === 'approved';
    }

    public function categoryLabel(): string
    {
        return __(self::CATEGORIES[$this->category] ?? ucfirst($this->category));
    }

    /** Title read by the cover artwork generator. */
    public function getTitleAttribute(): string
    {
        return (string) $this->name;
    }

    /** The podcaster's own picture, or a drawn cover. */
    public function coverUrl(): string
    {
        $media = $this->getFirstMedia('cover');

        return $media ? ($media->hasGeneratedConversion('card') ? $media->getUrl('card') : $media->getUrl()) : ContentArtwork::url('podcast', $this);
    }

    /** Re-counts episodes and the newest one (after adding, changing or removing an episode). */
    public function refreshCounts(): void
    {
        $published = $this->episodes()->out();
        $this->forceFill([
            'episodes_count' => (clone $published)->count(),
            'latest_episode_at' => (clone $published)->max('published_at'),
            'followers_count' => $this->followers()->count(),
        ])->saveQuietly();
    }
}
