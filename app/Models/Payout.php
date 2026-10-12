<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    use Auditable, BelongsToChurch;

    protected $fillable = ['church_id', 'amount', 'currency', 'period_start', 'period_end', 'status', 'method', 'reference', 'paid_at', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'period_start' => 'date', 'period_end' => 'date', 'paid_at' => 'datetime'];
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function auditLabel(): ?string
    {
        return 'Payout '.$this->period_start?->toDateString().' to '.$this->period_end?->toDateString();
    }
}
