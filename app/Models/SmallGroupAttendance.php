<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmallGroupAttendance extends Model
{
    use BelongsToChurch;

    protected $table = 'small_group_attendance';

    protected $fillable = ['church_id', 'small_group_meeting_id', 'small_group_id', 'user_id', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
