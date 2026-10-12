<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GivingChannel extends Model
{
    use Auditable, BelongsToChurch;

    /** Channels a member can pay through online. */
    public const ONLINE = ['iotec', 'mtn_momo', 'airtel_money', 'stripe'];

    /** Methods that need a mobile money phone number. */
    public const PHONE_METHODS = ['iotec', 'mtn_momo', 'airtel_money'];

    protected $fillable = ['church_id', 'type', 'label', 'details', 'instructions', 'is_active', 'sort'];

    protected $hidden = ['details'];

    protected function casts(): array
    {
        return ['details' => 'encrypted:array', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort');
    }

    public function isOnline(): bool
    {
        return in_array($this->type, self::ONLINE, true);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->label ?: config('faithfellows.payment_methods.'.$this->type, $this->type);
    }

    public function auditLabel(): ?string
    {
        return $this->display_name;
    }
}
