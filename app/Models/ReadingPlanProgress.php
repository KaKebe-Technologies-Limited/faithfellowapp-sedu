<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlanProgress extends Model
{
    use BelongsToChurch;

    protected $table = 'reading_plan_progress';

    protected $fillable = [
        'church_id', 'reading_plan_id', 'user_id', 'started_on', 'completed_days', 'current_streak',
        'longest_streak', 'last_read_on', 'reminder_time', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'last_read_on' => 'date',
            'completed_days' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ReadingPlan::class, 'reading_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The day the reader should be on today (calendar-based), capped to the plan length. */
    public function todaysDay(): int
    {
        return min($this->plan->days_count, $this->started_on->diffInDays(today()) + 1);
    }

    /** The next unread day — what "Day 47 of 365" shows. */
    public function nextDay(): int
    {
        $done = $this->completed_days ?? [];
        for ($d = 1; $d <= $this->plan->days_count; $d++) {
            if (! in_array($d, $done, true)) {
                return $d;
            }
        }

        return $this->plan->days_count;
    }

    public function percent(): float
    {
        return round(count($this->completed_days ?? []) / max(1, $this->plan->days_count) * 100, 1);
    }

    /**
     * Mark a day as read and update streaks (consecutive calendar days of reading).
     */
    public function markRead(int $day): void
    {
        $days = collect($this->completed_days ?? [])->push($day)->unique()->sort()->values()->all();

        $streak = match (true) {
            $this->last_read_on?->isToday() => $this->current_streak,
            $this->last_read_on?->isYesterday() => $this->current_streak + 1,
            default => 1,
        };

        $this->fill([
            'completed_days' => $days,
            'current_streak' => $streak,
            'longest_streak' => max($this->longest_streak, $streak),
            'last_read_on' => today(),
            'completed_at' => count($days) >= $this->plan->days_count ? ($this->completed_at ?? now()) : null,
        ])->save();
    }
}
