<?php

namespace App\Models\Concerns;

use App\Models\Church;
use App\Models\NotificationPreference;
use App\Models\SmallGroupMember;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "One account, many churches": church subscriptions for a User.
 */
trait HasSubscriptions
{
    /** @var array<int, int>|null */
    protected ?array $subscribedChurchIdCache = null;

    /** @var array<int, int>|null */
    protected ?array $memberChurchIdCache = null;

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscribedChurches(): BelongsToMany
    {
        return $this->belongsToMany(Church::class, 'subscriptions')
            ->withPivot(['id', 'status', 'stage', 'branch_id', 'subscribed_at'])
            ->wherePivot('status', 'active')
            ->withTimestamps();
    }

    public function smallGroupMemberships(): HasMany
    {
        return $this->hasMany(SmallGroupMember::class);
    }

    /**
     * Churches where the user may see pastor-only prayer requests.
     *
     * @return array<int, int>
     */
    public function pastoralChurchIds(): array
    {
        if ($this->isSuperAdmin()) {
            return [];
        }

        return $this->staffMemberships()->with('role')->where('status', 'active')->get()
            ->filter(fn ($s) => $s->is_admin || array_intersect(['prayer.moderate', 'care.manage'], $s->role?->permissions ?? []))
            ->pluck('church_id')->map(fn ($id) => (int) $id)->values()->all();
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Ids of churches the user actively follows.
     *
     * @return array<int, int>
     */
    public function subscribedChurchIds(): array
    {
        return $this->subscribedChurchIdCache ??= $this->subscriptions()->where('status', 'active')
            ->pluck('church_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Churches whose members-only content the user may see (subscriber or staff).
     *
     * @return array<int, int>
     */
    public function memberChurchIds(): array
    {
        return $this->memberChurchIdCache ??= array_values(array_unique([
            ...$this->subscribedChurchIds(),
            ...$this->staffMemberships()->where('status', 'active')->pluck('church_id')->map(fn ($id) => (int) $id)->all(),
        ]));
    }

    public function isSubscribedTo(Church|int $church): bool
    {
        return in_array($church instanceof Church ? $church->id : $church, $this->subscribedChurchIds(), true);
    }

    public function isMemberOf(Church|int $church): bool
    {
        return in_array($church instanceof Church ? $church->id : $church, $this->memberChurchIds(), true);
    }

    public function flushSubscriptionCache(): void
    {
        $this->subscribedChurchIdCache = null;
        $this->memberChurchIdCache = null;
    }
}
