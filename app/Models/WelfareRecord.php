<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WelfareRecord extends Model
{
    use Auditable, BelongsToChurch;

    public const TYPES = ['food' => 'Food', 'cash' => 'Cash support', 'medical' => 'Medical', 'school_fees' => 'School fees', 'funeral' => 'Funeral support', 'housing' => 'Housing', 'other' => 'Other'];

    protected $fillable = ['church_id', 'user_id', 'recipient_name', 'type', 'amount', 'given_on', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['given_on' => 'date', 'amount' => 'decimal:2', 'notes' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRecipientAttribute(): string
    {
        return $this->user?->name ?? $this->recipient_name ?? '—';
    }

    public function auditLabel(): ?string
    {
        return 'Welfare: '.$this->type;
    }
}
