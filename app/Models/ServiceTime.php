<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ServiceTime extends Model
{
    use BelongsToChurch;

    public const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    protected $fillable = ['church_id', 'branch_id', 'name', 'day_of_week', 'start_time', 'end_time', 'language', 'location', 'description'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getDayNameAttribute(): string
    {
        return __(self::DAYS[$this->day_of_week] ?? 'Sunday');
    }

    public function getTimeRangeAttribute(): string
    {
        $start = Carbon::parse($this->start_time)->format('g:i A');

        return $this->end_time ? $start.' – '.Carbon::parse($this->end_time)->format('g:i A') : $start;
    }
}
