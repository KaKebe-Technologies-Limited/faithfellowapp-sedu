<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A multiple-choice quiz written by church admins and played live.
 */
class Quiz extends Model
{
    use Auditable, BelongsToChurch, SoftDeletes;

    protected $fillable = ['church_id', 'user_id', 'title', 'description', 'theme'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }
}
