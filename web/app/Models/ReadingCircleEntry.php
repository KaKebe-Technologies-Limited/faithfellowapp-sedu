<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's reading of one plan day: when they finished and their summary.
 * Summaries are encrypted and only shown to members of the same circle.
 */
class ReadingCircleEntry extends Model
{
    protected $fillable = ['reading_circle_id', 'user_id', 'day', 'summary', 'completed_at'];

    protected $hidden = ['summary'];

    protected function casts(): array
    {
        return ['summary' => 'encrypted', 'completed_at' => 'datetime'];
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(ReadingCircle::class, 'reading_circle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
