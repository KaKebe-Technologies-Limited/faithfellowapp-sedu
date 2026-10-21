<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * A track put on air at a set time, once or every day or every week. When the time comes the channel moves to the track and carries on from it,
 * like "Play now" pressed at that moment. Times are in the app's time zone (East Africa Time). Before the database update that adds the table,
 * nothing is scheduled and the radio works as before (see {@see ready()}).
 */
class RadioSchedule extends Model
{
    public const REPEATS = ['none' => 'Once', 'daily' => 'Every day', 'weekly' => 'Every week'];

    protected $fillable = ['station', 'radio_track_id', 'starts_at', 'repeat', 'is_active', 'created_by'];

    private static ?bool $ready = null;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public static function ready(): bool
    {
        return self::$ready ??= rescue(fn () => Schema::hasTable('radio_schedules'), false, false);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(RadioTrack::class, 'radio_track_id');
    }

    public function repeatLabel(): string
    {
        return __(self::REPEATS[$this->repeat] ?? 'Once');
    }

    /** The last time it went on air at or before this moment, or null when it has not started yet. */
    public function occurrenceAt(CarbonInterface $at): ?Carbon
    {
        $start = $this->starts_at->copy();
        if ($start->gt($at)) {
            return null;
        }

        return match ($this->repeat) {
            'daily' => $start->addDays((int) floor($start->diffInDays($at, true))),
            'weekly' => $start->addWeeks(intdiv((int) floor($start->diffInDays($at, true)), 7)),
            default => $start,
        };
    }

    /** The next time it goes on air after this moment, or null when it will not again. */
    public function nextAfter(CarbonInterface $at): ?Carbon
    {
        $start = $this->starts_at->copy();
        if ($start->gt($at)) {
            return $start;
        }

        return match ($this->repeat) {
            'daily' => $this->occurrenceAt($at)->addDay(),
            'weekly' => $this->occurrenceAt($at)->addWeek(),
            default => null,
        };
    }
}
