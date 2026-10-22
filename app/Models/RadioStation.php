<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A channel that plays round the clock, in a loop: "gospel" (songs) and "podcast" (teaching and podcasts) belong to the platform and are set
 * by the super admin; every church can have its own channel (slug "church-{id}") that it fills itself. The rows are made the first time they are
 * needed. Before the database update that adds the table, the Gospel radio still works as one channel without a row (see {@see ready()}).
 */
class RadioStation extends Model
{
    public const GOSPEL = 'gospel';

    public const PODCAST = 'podcast';

    protected $fillable = ['church_id', 'slug', 'kind', 'name', 'is_enabled', 'is_blocked'];

    private static ?bool $ready = null;

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'is_blocked' => 'boolean'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(PodcastComment::class, 'commentable');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(RadioTrack::class, 'station_id');
    }

    /** True once the database update that adds the stations has been applied. */
    public static function ready(): bool
    {
        return self::$ready ??= rescue(fn () => Schema::hasTable('radio_stations') && Schema::hasColumn('radio_tracks', 'station_id'), false, false);
    }

    /** The platform channel with this slug ("gospel" or "podcast"). Without the table it is a channel that is not saved. */
    public static function platform(string $slug = self::GOSPEL): static
    {
        $slug = $slug === self::PODCAST ? self::PODCAST : self::GOSPEL;
        $defaults = $slug === self::PODCAST
            ? ['kind' => 'talk', 'name' => 'FaithFellows Podcast Radio', 'is_enabled' => true]
            : ['kind' => 'music', 'name' => (string) (PlatformSetting::get('radio_name') ?: 'FaithFellows Radio'), 'is_enabled' => (bool) PlatformSetting::get('radio_enabled', '1')];

        return self::ready()
            ? static::firstOrCreate(['slug' => $slug], $defaults)
            : new static(['slug' => $slug] + $defaults);
    }

    public static function gospel(): static
    {
        return self::platform(self::GOSPEL);
    }

    public static function podcast(): static
    {
        return self::platform(self::PODCAST);
    }

    /** A church's own channel. */
    public static function forChurch(Church $church): static
    {
        return static::firstOrCreate(
            ['slug' => 'church-'.$church->id],
            ['church_id' => $church->id, 'kind' => 'talk', 'name' => Str::limit($church->name, 68, '').' Radio', 'is_enabled' => true],
        );
    }

    public function isGospel(): bool
    {
        return $this->slug === self::GOSPEL;
    }

    public function isPlatform(): bool
    {
        return $this->church_id === null;
    }

    /** Songs for a music channel, episodes for a talk channel. */
    public function noun(): string
    {
        return $this->kind === 'music' ? 'song' : 'episode';
    }

    public function tagline(): string
    {
        return match (true) {
            $this->kind === 'music' => __('Gospel music, day and night'),
            $this->isPlatform() => __('Teaching and podcasts, day and night'),
            default => __('Teaching from our church, day and night'),
        };
    }
}
