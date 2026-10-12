<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerRota extends Model
{
    use BelongsToChurch;

    protected $fillable = ['church_id', 'volunteer_team_id', 'user_id', 'service_date', 'role', 'status', 'notes', 'reminded_at'];

    protected function casts(): array
    {
        return ['service_date' => 'date', 'reminded_at' => 'datetime'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(VolunteerTeam::class, 'volunteer_team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
