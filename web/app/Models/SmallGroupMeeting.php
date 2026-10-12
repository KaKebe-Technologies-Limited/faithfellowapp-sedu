<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmallGroupMeeting extends Model
{
    use BelongsToChurch;

    protected $fillable = ['church_id', 'small_group_id', 'meeting_date', 'topic', 'visitors', 'offering_amount', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['meeting_date' => 'date', 'offering_amount' => 'decimal:2'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SmallGroup::class, 'small_group_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(SmallGroupAttendance::class);
    }
}
