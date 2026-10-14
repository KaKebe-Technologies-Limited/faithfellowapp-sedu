<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizPlayer extends Model
{
    protected $fillable = ['quiz_session_id', 'user_id', 'nickname', 'score', 'correct_count', 'streak'];

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
