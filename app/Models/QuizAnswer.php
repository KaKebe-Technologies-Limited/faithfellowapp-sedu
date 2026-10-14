<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAnswer extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['quiz_session_id', 'quiz_player_id', 'quiz_question_id', 'option', 'is_correct', 'points', 'response_ms'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(QuizPlayer::class, 'quiz_player_id');
    }
}
