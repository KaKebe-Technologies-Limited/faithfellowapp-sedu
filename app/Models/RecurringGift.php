<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RecurringGift extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'church_id', 'user_id', 'campaign_id', 'category', 'amount', 'currency', 'frequency', 'method', 'phone',
        'next_run_on', 'last_run_at', 'failures', 'status',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'next_run_on' => 'date', 'last_run_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function nextDateAfter(Carbon $date): Carbon
    {
        return match ($this->frequency) {
            'weekly' => $date->copy()->addWeek(),
            'quarterly' => $date->copy()->addMonthsNoOverflow(3),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }
}
