<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMilestone extends Model
{
    use BelongsToChurch;

    protected $fillable = ['church_id', 'campaign_id', 'percent', 'title', 'reached_at'];

    protected function casts(): array
    {
        return ['reached_at' => 'datetime', 'percent' => 'integer'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
