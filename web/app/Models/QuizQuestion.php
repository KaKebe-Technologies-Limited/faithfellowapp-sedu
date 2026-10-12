<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    protected $fillable = ['quiz_id', 'position', 'question', 'options', 'correct_option', 'time_limit', 'points'];

    protected $hidden = ['correct_option'];

    protected function casts(): array
    {
        return ['options' => 'array', 'correct_option' => 'integer', 'time_limit' => 'integer', 'points' => 'integer'];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
