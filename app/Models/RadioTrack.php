<?php

namespace App\Models;

use App\Support\ContentArtwork;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One song of the gospel radio. The file lives on the private disk and is streamed through
 * RadioFileController (with range support, so phones can start part-way into a song).
 */
class RadioTrack extends Model
{
    protected $fillable = ['station_id', 'title', 'artist', 'file_path', 'mime', 'size', 'duration_seconds', 'cover_path', 'position', 'is_active', 'uploaded_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'size' => 'integer', 'duration_seconds' => 'integer', 'position' => 'integer'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(RadioStation::class, 'station_id');
    }

    /** The channel this track plays on (the Gospel radio when it has none). */
    public function channel(): RadioStation
    {
        return ($this->station_id && RadioStation::ready() ? $this->station : null) ?? RadioStation::gospel();
    }

    /**
     * The tracks of one channel. The Gospel radio also owns the songs added before channels existed (no channel), and a channel that has
     * not been saved yet (before the database update) is every track.
     */
    public function scopeOfStation(Builder $query, RadioStation $station): Builder
    {
        if (! $station->exists) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($station) {
            $q->where('station_id', $station->id);
            if ($station->isGospel()) {
                $q->orWhereNull('station_id');
            }
        });
    }

    /** Songs that are in the loop, in order. */
    public function scopeOnAir(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('duration_seconds', '>', 0)->orderBy('position')->orderBy('id');
    }

    public function path(): string
    {
        return Storage::disk('local')->path($this->file_path);
    }

    public function coverFile(): ?string
    {
        return $this->cover_path && Storage::disk('local')->exists($this->cover_path) ? Storage::disk('local')->path($this->cover_path) : null;
    }

    public function streamUrl(): string
    {
        return route('radio.stream', $this);
    }

    /** The picture from the song file, or a generated worship scene when it has none. */
    public function coverUrl(): string
    {
        return $this->coverFile() ? route('radio.cover', $this).'?v='.$this->updated_at?->timestamp : ContentArtwork::url('radio', $this);
    }

    /** What the app and the player need to know about the song. */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'artist' => $this->artist,
            'duration' => (int) $this->duration_seconds,
            'cover_url' => $this->coverUrl(),
            'stream_url' => $this->streamUrl(),
        ];
    }
}
