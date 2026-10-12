<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pledge extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'church_id', 'user_id', 'campaign_id', 'category', 'amount', 'paid_amount', 'currency', 'frequency',
        'installments', 'start_date', 'due_date', 'next_reminder_on', 'status', 'note', 'is_anonymous',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'start_date' => 'date', 'due_date' => 'date',
            'next_reminder_on' => 'date', 'is_anonymous' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function balance(): float
    {
        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }

    public function percent(): float
    {
        return $this->amount > 0 ? round(min(100, (float) $this->paid_amount / (float) $this->amount * 100), 1) : 0;
    }

    /** Suggested size of each installment. */
    public function installmentAmount(): float
    {
        return round((float) $this->amount / max(1, $this->installments), 0);
    }
}
