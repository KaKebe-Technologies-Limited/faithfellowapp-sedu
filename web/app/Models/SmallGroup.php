<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SmallGroup extends Model
{
    use Auditable, BelongsToChurch, SoftDeletes;

    public const TYPES = ['cell' => 'Cell', 'home_fellowship' => 'Home fellowship', 'bible_study' => 'Bible study', 'youth' => 'Youth', 'women' => 'Women', 'men' => 'Men', 'ministry' => 'Ministry team'];

    protected $fillable = [
        'church_id', 'branch_id', 'name', 'slug', 'type', 'description', 'leader_id', 'meeting_day', 'meeting_time',
        'location', 'lat', 'lng', 'capacity', 'is_open', 'status',
    ];

    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'lat' => 'float', 'lng' => 'float', 'meeting_day' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (SmallGroup $group) {
            if (! $group->slug) {
                $base = Str::slug($group->name) ?: 'group';
                $slug = $base;
                for ($i = 2; static::withTrashed()->where('church_id', $group->church_id)->where('slug', $slug)->exists(); $i++) {
                    $slug = $base.'-'.$i;
                }
                $group->slug = $slug;
            }
        });
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SmallGroupMember::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'small_group_members')->withPivot(['role', 'status', 'joined_at'])->wherePivot('status', 'active');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(SmallGroupMeeting::class)->latest('meeting_date');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /** Group leaders (and assistants) may run their own group. */
    public function isLedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->leader_id === $user->id
            || $this->memberships()->where('user_id', $user->id)->whereIn('role', ['leader', 'assistant'])->exists();
    }

    public function getScheduleAttribute(): ?string
    {
        if ($this->meeting_day === null) {
            return null;
        }

        return __(ServiceTime::DAYS[$this->meeting_day]).($this->meeting_time ? ' · '.Carbon::parse($this->meeting_time)->format('g:i A') : '');
    }
}
