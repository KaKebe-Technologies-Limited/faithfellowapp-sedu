<?php

namespace App\Models;

use App\Models\Concerns\HasChurchMemberships;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasChurchMemberships, HasFactory, HasRoles, InteractsWithMedia, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'country', 'city', 'language', 'timezone',
        'gender', 'date_of_birth', 'wedding_anniversary', 'bio', 'status',
        'quiet_hours_start', 'quiet_hours_end', 'digest_mode',
        'email_consent', 'terms_accepted_at', 'directory_opt_in', 'low_bandwidth',
        'two_factor_enabled',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_code'];

    /** Mirror DB defaults so new instances behave correctly before a refresh. */
    protected $attributes = [
        'status' => 'active',
        'country' => 'UG',
        'language' => 'en',
        'timezone' => 'Africa/Kampala',
        'digest_mode' => false,
        'email_consent' => false,
        'directory_opt_in' => false,
        'low_bandwidth' => false,
        'two_factor_enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'wedding_anniversary' => 'date',
            'terms_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_expires_at' => 'datetime',
            'digest_mode' => 'boolean',
            'email_consent' => 'boolean',
            'directory_opt_in' => 'boolean',
            'low_bandwidth' => 'boolean',
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(160)->height(160)->nonQueued();
    }

    // ── Accessors ────────────────────────────────────────────────────────

    public function getAvatarUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('avatar');

        return $media ? $media->getUrl('thumb') : null;
    }

    public function getInitialsAttribute(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))->implode('');
    }

    public function getFirstNameAttribute(): string
    {
        return Str::before($this->name, ' ') ?: $this->name;
    }

    // ── Roles & routing ──────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isActive(): bool
    {
        return $this->status !== 'suspended';
    }

    /**
     * Role-aware landing page after login.
     */
    public function homeUrl(): string
    {
        if ($this->isSuperAdmin()) {
            return route('super-admin.dashboard');
        }

        $church = $this->staffChurches()->wherePivot('status', 'active')->first();
        if ($church && $church->status !== 'archived') {
            return route('church-admin.dashboard', $church);
        }

        return route('dashboard');
    }

    /**
     * Quiet hours check in the user's own timezone.
     */
    public function inQuietHours(?\DateTimeInterface $at = null): bool
    {
        if (! $this->quiet_hours_start || ! $this->quiet_hours_end) {
            return false;
        }

        $now = now()->setTimezone($this->timezone ?: config('app.timezone'));
        if ($at) {
            $now = Carbon::instance($at)->setTimezone($this->timezone ?: config('app.timezone'));
        }
        $time = $now->format('H:i:s');
        $start = strlen($this->quiet_hours_start) === 5 ? $this->quiet_hours_start.':00' : $this->quiet_hours_start;
        $end = strlen($this->quiet_hours_end) === 5 ? $this->quiet_hours_end.':00' : $this->quiet_hours_end;

        // Window may wrap past midnight (e.g. 22:00 → 06:00).
        return $start <= $end
            ? ($time >= $start && $time < $end)
            : ($time >= $start || $time < $end);
    }

    /**
     * Next moment the quiet-hours window ends (used to delay notifications).
     */
    public function quietHoursEndAt(): Carbon
    {
        $tz = $this->timezone ?: config('app.timezone');
        $end = now($tz)->setTimeFromTimeString($this->quiet_hours_end ?? '06:00');
        if ($end->isPast()) {
            $end->addDay();
        }

        return $end->setTimezone(config('app.timezone'));
    }

    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    public function routeNotificationForWhatsapp(): ?string
    {
        return $this->phone;
    }
}
