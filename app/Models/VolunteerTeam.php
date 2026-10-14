<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolunteerTeam extends Model
{
    use BelongsToChurch;

    protected $fillable = ['church_id', 'name', 'description', 'leader_id', 'color'];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'volunteer_team_members')->withPivot('church_id')->withTimestamps();
    }

    public function rotas(): HasMany
    {
        return $this->hasMany(VolunteerRota::class);
    }
}
