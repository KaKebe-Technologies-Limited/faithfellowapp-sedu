<?php

namespace App\Models\Concerns;

use App\Models\Church;
use App\Models\ChurchRole;
use App\Models\ChurchStaff;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Church staff membership + per-church permission checks for User.
 */
trait HasChurchMemberships
{
    /** @var array<int, array<int, string>|null> per-request permission cache */
    protected array $churchPermissionCache = [];

    public function staffChurches(): BelongsToMany
    {
        return $this->belongsToMany(Church::class, 'church_staff')
            ->using(ChurchStaff::class)
            ->withPivot(['id', 'church_role_id', 'branch_id', 'is_admin', 'title', 'status'])
            ->withTimestamps();
    }

    public function staffMemberships(): HasMany
    {
        return $this->hasMany(ChurchStaff::class);
    }

    public function staffMembershipFor(Church|int $church): ?ChurchStaff
    {
        $churchId = $church instanceof Church ? $church->id : $church;

        return $this->staffMemberships()->with('role')
            ->where('church_id', $churchId)->where('status', 'active')->first();
    }

    public function isStaffOf(Church|int $church): bool
    {
        return $this->isSuperAdmin() || $this->churchPermissions($church) !== null;
    }

    public function isChurchAdmin(Church|int $church): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return (bool) $this->staffMembershipFor($church)?->is_admin;
    }

    /**
     * All permissions the user holds in a church, or null if not staff.
     *
     * @return array<int, string>|null
     */
    public function churchPermissions(Church|int $church): ?array
    {
        $churchId = $church instanceof Church ? $church->id : $church;

        if (array_key_exists($churchId, $this->churchPermissionCache)) {
            return $this->churchPermissionCache[$churchId];
        }

        $membership = $this->staffMembershipFor($churchId);

        $permissions = match (true) {
            $membership === null => null,
            $membership->is_admin => ChurchRole::catalog(),
            default => $membership->role?->permissions ?? [],
        };

        return $this->churchPermissionCache[$churchId] = $permissions;
    }

    /**
     * Server-side RBAC check used by policies, middleware and views.
     */
    public function canInChurch(Church|int $church, string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->churchPermissions($church) ?? [], true);
    }

    public function flushChurchPermissionCache(): void
    {
        $this->churchPermissionCache = [];
    }
}
