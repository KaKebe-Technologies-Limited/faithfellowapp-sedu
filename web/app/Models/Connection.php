<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request between two members to be connected; once accepted they can message each other.
 */
class Connection extends Model
{
    protected $fillable = ['requester_id', 'addressee_id', 'status', 'message', 'responded_at'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /** The connection between two members, whichever of them asked. */
    public function scopeBetween(Builder $query, int $a, int $b): Builder
    {
        return $query->where(fn ($q) => $q->where(fn ($w) => $w->where('requester_id', $a)->where('addressee_id', $b))
            ->orWhere(fn ($w) => $w->where('requester_id', $b)->where('addressee_id', $a)));
    }

    /** All connections a member is part of. */
    public function scopeInvolving(Builder $query, int $userId): Builder
    {
        return $query->where(fn ($q) => $q->where('requester_id', $userId)->orWhere('addressee_id', $userId));
    }

    public function otherThan(User|int $user): int
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->requester_id === $id ? $this->addressee_id : $this->requester_id;
    }
}
