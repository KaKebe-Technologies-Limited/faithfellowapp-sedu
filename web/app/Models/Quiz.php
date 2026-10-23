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

    protected $fillable = ['church_id', 'user_id', 'title', 'description', 'theme', 'category', 'emoji', 'is_sample'];

    protected function casts(): array
    {
        return ['is_sample' => 'boolean'];
    }

    /** A ready-made community game (no church) that any member can start. */
    public function scopeSamples($query)
    {
        return $query->where('is_sample', true)->whereNull('church_id');
    }

    /**
     * What a member can play on their own: the ready-made games, and the quizzes of the churches they follow.
     *
     * @param  array<int, int>  $churchIds
     */
    public function scopePlayableBy($query, array $churchIds)
    {
        return $query->where(fn ($q) => $q->where(fn ($s) => $s->where('is_sample', true)->whereNull('church_id'))->orWhereIn('church_id', $churchIds ?: [0]));
    }

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
