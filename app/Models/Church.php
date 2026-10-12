<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Church extends Model implements HasMedia
{
    use Auditable, HasFactory, InteractsWithMedia, SoftDeletes;

    public const STATUSES = ['pending', 'active', 'suspended', 'archived'];

    /** Default website builder layout for new churches. */
    public const DEFAULT_BLOCKS = [
        ['type' => 'hero', 'enabled' => true],
        ['type' => 'live', 'enabled' => true],
        ['type' => 'about', 'enabled' => true],
        ['type' => 'service_times', 'enabled' => true],
        ['type' => 'sermons', 'enabled' => true],
        ['type' => 'events', 'enabled' => true],
        ['type' => 'campaigns', 'enabled' => true],
        ['type' => 'giving', 'enabled' => true],
        ['type' => 'prayer', 'enabled' => true],
        ['type' => 'contact', 'enabled' => true],
    ];

    protected $fillable = [
        'owner_id', 'name', 'slug', 'denomination', 'tagline', 'description', 'about', 'pastor_name', 'founded_year',
        'languages', 'country', 'district', 'city', 'address', 'lat', 'lng', 'phone', 'email', 'website',
        'brand_color', 'website_blocks', 'settings', 'custom_domain', 'custom_domain_verified',
        'status', 'application_notes', 'status_reason', 'approved_at', 'approved_by', 'setup_completed_at',
        'requires_member_approval', 'is_featured', 'plan_id', 'billing_status', 'trial_ends_at', 'paid_until', 'domain_addon',
    ];

    protected $attributes = [
        'status' => 'pending',
        'country' => 'UG',
        'brand_color' => '#BC2649',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'website_blocks' => 'array',
            'settings' => 'array',
            'lat' => 'float',
            'lng' => 'float',
            'approved_at' => 'datetime',
            'setup_completed_at' => 'datetime',
            'custom_domain_verified' => 'boolean',
            'requires_member_approval' => 'boolean',
            'is_featured' => 'boolean',
            'trial_ends_at' => 'date',
            'paid_until' => 'date',
            'domain_addon' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Generate a unique, URL- and subdomain-safe slug.
     */
    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::of($name)->slug()->limit(60, '')->trim('-')->toString() ?: 'church';
        $reserved = ['www', 'app', 'api', 'admin', 'mail', 'super-admin', 'church', 'churches', 'live', 'static', 'cdn', 'help', 'support', 'blog'];
        $slug = in_array($base, $reserved, true) ? $base.'-church' : $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    // ── Media ────────────────────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']);
        $this->addMediaCollection('cover')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
        $this->addMediaCollection('gallery');
        // General-purpose media library uploads (images, audio, documents).
        $this->addMediaCollection('library');
    }

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        $this->addMediaConversion('thumb')->width(240)->height(240)->nonQueued()->performOnCollections('logo', 'gallery', 'library');
        $this->addMediaConversion('banner')->width(1600)->height(600)->nonQueued()->performOnCollections('cover');
    }

    public function getLogoUrlAttribute(): ?string
    {
        $media = $this->relationLoaded('media') ? $this->media->firstWhere('collection_name', 'logo') : $this->getFirstMedia('logo');

        return $media ? ($media->mime_type === 'image/svg+xml' ? $media->getUrl() : $media->getUrl('thumb')) : null;
    }

    public function getCoverUrlAttribute(): ?string
    {
        $media = $this->relationLoaded('media') ? $this->media->firstWhere('collection_name', 'cover') : $this->getFirstMedia('cover');

        return $media?->getUrl('banner');
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function serviceTimes(): HasMany
    {
        return $this->hasMany(ServiceTime::class)->orderBy('day_of_week')->orderBy('start_time');
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(ChurchSocialLink::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(ChurchRole::class);
    }

    public function staffMembers(): HasMany
    {
        return $this->hasMany(ChurchStaff::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'church_staff')
            ->using(ChurchStaff::class)
            ->withPivot(['id', 'church_role_id', 'branch_id', 'is_admin', 'title', 'status'])
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subscriptions')
            ->withPivot(['id', 'status', 'stage', 'branch_id', 'subscribed_at'])
            ->wherePivot('status', 'active')
            ->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function sermons(): HasMany
    {
        return $this->hasMany(Sermon::class);
    }

    /** Named "series" so {series} route params resolve via scoped bindings. */
    public function series(): HasMany
    {
        return $this->hasMany(SermonSeries::class);
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    public function devotionals(): HasMany
    {
        return $this->hasMany(Devotional::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function readingPlans(): HasMany
    {
        return $this->hasMany(ReadingPlan::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function liveStreams(): HasMany
    {
        return $this->hasMany(LiveStream::class);
    }

    public function givingChannels(): HasMany
    {
        return $this->hasMany(GivingChannel::class)->orderBy('sort');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function pledges(): HasMany
    {
        return $this->hasMany(Pledge::class);
    }

    public function recurringGifts(): HasMany
    {
        return $this->hasMany(RecurringGift::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function prayerRequests(): HasMany
    {
        return $this->hasMany(PrayerRequest::class);
    }

    public function testimonies(): HasMany
    {
        return $this->hasMany(Testimony::class);
    }

    public function smallGroups(): HasMany
    {
        return $this->hasMany(SmallGroup::class);
    }

    public function volunteerTeams(): HasMany
    {
        return $this->hasMany(VolunteerTeam::class);
    }

    public function volunteerRotas(): HasMany
    {
        return $this->hasMany(VolunteerRota::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function pastoralNotes(): HasMany
    {
        return $this->hasMany(PastoralNote::class);
    }

    public function welfareRecords(): HasMany
    {
        return $this->hasMany(WelfareRecord::class);
    }

    public function homeVisits(): HasMany
    {
        return $this->hasMany(HomeVisit::class);
    }

    public function discipleshipRecords(): HasMany
    {
        return $this->hasMany(DiscipleshipRecord::class);
    }

    public function counselingAppointments(): HasMany
    {
        return $this->hasMany(CounselingAppointment::class);
    }

    public function bankStatementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function churchBroadcasts(): HasMany
    {
        return $this->hasMany(ChurchBroadcast::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function contentReports(): HasMany
    {
        return $this->hasMany(ContentReport::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /**
     * A usage limit from the church's plan (storage_mb, stream_hours, max_admins, max_branches).
     */
    public function planLimit(string $key): ?int
    {
        $column = match ($key) {
            'admins' => 'max_admins',
            'branches' => 'max_branches',
            default => $key,
        };
        $plan = $this->relationLoaded('plan') ? $this->plan : ($this->plan_id ? $this->plan()->first() : null);

        return $plan?->{$column} !== null ? (int) $plan->{$column} : null;
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // ── Scopes & helpers ─────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
            ->orWhere('city', 'like', "%{$term}%")
            ->orWhere('district', 'like', "%{$term}%")
            ->orWhere('denomination', 'like', "%{$term}%")
            ->orWhere('pastor_name', 'like', "%{$term}%"));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function blocks(): array
    {
        $blocks = $this->website_blocks ?: self::DEFAULT_BLOCKS;
        // Make sure newly introduced block types appear (disabled) for older churches.
        $known = collect($blocks)->pluck('type');
        foreach (self::DEFAULT_BLOCKS as $default) {
            if (! $known->contains($default['type'])) {
                $blocks[] = ['type' => $default['type'], 'enabled' => false];
            }
        }

        return $blocks;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    public function subdomainUrl(): string
    {
        return ff_church_url($this);
    }

    public function getLocationAttribute(): string
    {
        return collect([$this->city, $this->district !== $this->city ? $this->district : null])->filter()->implode(', ') ?: 'Uganda';
    }
}
