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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Event extends Model implements HasMedia
{
    use Auditable, BelongsToChurch, HasArtwork, HasChurchSlug, HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'church_id', 'branch_id', 'user_id', 'title', 'slug', 'description', 'location_name', 'address', 'lat', 'lng',
        'is_online', 'online_url', 'starts_at', 'ends_at', 'capacity', 'rsvp_enabled', 'is_paid', 'is_cross_branch',
        'status', 'visibility', 'agenda', 'speakers', 'qa_enabled', 'recording_url', 'recording_sermon_id',
        'reminder_24h_sent_at', 'reminder_1h_sent_at', 'notified_at',
    ];

    public function artworkKind(): string
    {
        return 'event';
    }

    public function uploadedPicture(): ?string
    {
        return $this->cover_url;
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_online' => 'boolean',
            'rsvp_enabled' => 'boolean',
            'is_paid' => 'boolean',
            'is_cross_branch' => 'boolean',
            'qa_enabled' => 'boolean',
            'agenda' => 'array',
            'speakers' => 'array',
            'lat' => 'float',
            'lng' => 'float',
            'reminder_24h_sent_at' => 'datetime',
            'reminder_1h_sent_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        $this->addMediaConversion('card')->width(960)->height(540)->nonQueued();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(EventTicket::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(EventQuestion::class);
    }

    public function recordingSermon(): BelongsTo
    {
        return $this->belongsTo(Sermon::class, 'recording_sermon_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('starts_at', '>=', now())
            ->orWhere(fn ($r) => $r->whereNotNull('ends_at')->where('ends_at', '>=', now())));
    }

    /**
     * @param  array<int, int>  $memberChurchIds
     */
    public function scopeVisibleTo(Builder $query, array $memberChurchIds): Builder
    {
        return $query->where(fn ($q) => $q->where('visibility', 'public')->orWhereIn('church_id', $memberChurchIds));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isPast(): bool
    {
        return ($this->ends_at ?? $this->starts_at->copy()->addHours(3))->isPast();
    }

    public function getCoverUrlAttribute(): ?string
    {
        $media = $this->relationLoaded('media') ? $this->media->firstWhere('collection_name', 'cover') : $this->getFirstMedia('cover');

        return $media?->getUrl('card');
    }

    /** Seats taken by confirmed / pending RSVPs. */
    public function seatsTaken(): int
    {
        return (int) $this->rsvps()->whereIn('status', ['going', 'pending_payment'])->sum('quantity');
    }

    public function seatsLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->seatsTaken());
    }
}
