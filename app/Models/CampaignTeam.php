<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignTeam extends Model
{
    use BelongsToChurch;

    protected $fillable = ['church_id', 'campaign_id', 'name', 'captain_id', 'goal_amount', 'raised_amount', 'color'];

    protected function casts(): array
    {
        return ['goal_amount' => 'decimal:2', 'raised_amount' => 'decimal:2'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function captain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captain_id');
    }

    public function percent(): float
    {
        return $this->goal_amount > 0 ? round(min(100, (float) $this->raised_amount / (float) $this->goal_amount * 100), 1) : 0;
    }
}
