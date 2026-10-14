<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToChurch;

    protected $fillable = ['church_id', 'user_id', 'branch_id', 'service_time_id', 'service_date', 'method', 'guest_name', 'headcount', 'recorded_by'];

    protected function casts(): array
    {
        return ['service_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceTime(): BelongsTo
    {
        return $this->belongsTo(ServiceTime::class);
    }
}
