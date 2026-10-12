<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Campaign extends Model implements HasMedia
{
    use Auditable, BelongsToChurch, HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'church_id', 'branch_id', 'user_id', 'title', 'slug', 'type', 'short_description', 'story', 'goal_amount', 'currency',
        'raised_amount', 'pledged_amount', 'givers_count', 'starts_at', 'ends_at', 'status', 'visibility',
        'show_leaderboard', 'allow_anonymous', 'is_featured', 'goal_reached_at',
    ];

    protected function casts(): array
    {
        return [
            'goal_amount' => 'decimal:2',
            'raised_amount' => 'decimal:2',
            'pledged_amount' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'show_leaderboard' => 'boolean',
            'allow_anonymous' => 'boolean',
            'is_featured' => 'boolean',
            'goal_reached_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign) {
            $campaign->slug ??= static::uniqueSlug($campaign->title, $campaign->church_id);
        });
    }

    public static function uniqueSlug(string $title, ?int $churchId = null): string
    {
        $base = Str::slug($title) ?: 'campaign';
        $slug = $base;
        if (static::withTrashed()->where('slug', $slug)->exists() && $churchId) {
            $slug = $base.'-'.(Church::find($churchId)?->slug ?? $churchId);
        }
        for ($i = 2; static::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
        $this->addMediaCollection('gallery')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        $this->addMediaConversion('card')->width(960)->height(540)->nonQueued();
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function milestones(): HasMany
    {
        return $this->hasMany(CampaignMilestone::class)->orderBy('percent');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(CampaignUpdate::class)->latest('published_at');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(CampaignTeam::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function pledges(): HasMany
    {
        return $this->hasMany(Pledge::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Scopes & helpers ─────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  array<int, int>  $memberChurchIds
     */
    public function scopeVisibleTo(Builder $query, array $memberChurchIds): Builder
    {
        return $query->where(fn ($q) => $q->where('visibility', 'public')->orWhereIn('church_id', $memberChurchIds));
    }

    public function percent(): float
    {
        return $this->goal_amount > 0 ? round(min(999, (float) $this->raised_amount / (float) $this->goal_amount * 100), 1) : 0;
    }

    public function remaining(): float
    {
        return max(0, (float) $this->goal_amount - (float) $this->raised_amount);
    }

    public function daysLeft(): ?int
    {
        return $this->ends_at ? max(0, (int) today()->diffInDays($this->ends_at, false)) : null;
    }

    public function isOpen(): bool
    {
        return $this->status === 'active' && (! $this->ends_at || ! $this->ends_at->isPast() || $this->ends_at->isToday());
    }

    public function getCoverUrlAttribute(): ?string
    {
        $media = $this->relationLoaded('media') ? $this->media->firstWhere('collection_name', 'cover') : $this->getFirstMedia('cover');

        return $media?->getUrl('card');
    }

    public function getTypeLabelAttribute(): string
    {
        return config('faithfellows.campaign_types.'.$this->type, Str::headline($this->type));
    }
}
