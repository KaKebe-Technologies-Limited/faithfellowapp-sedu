<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use BelongsToChurch;

    public const STAGES = [
        'visitor' => 'Visitor',
        'new_member' => 'New member',
        'member' => 'Member',
        'worker' => 'Worker',
        'leader' => 'Leader',
    ];

    protected $fillable = [
        'church_id', 'user_id', 'branch_id', 'status', 'stage', 'share_email', 'source',
        'subscribed_at', 'unsubscribed_at', 'approved_at',
    ];

    protected $attributes = ['status' => 'active', 'stage' => 'visitor'];

    protected function casts(): array
    {
        return [
            'share_email' => 'boolean',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
