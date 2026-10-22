<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizPlayer extends Model
{
    protected $fillable = ['quiz_session_id', 'user_id', 'nickname', 'score', 'correct_count', 'streak'];

    /** Millisecond precision, for the clock of a self-paced run (the player's own question and finish times). */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return [
            'question_started_at' => 'datetime',
            'revealed_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'current_index' => 'integer',
            'total_ms' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }
}
