<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One live game of a quiz: lobby → question → reveal → … → finished.
 * After the host starts it, QuizService::advance() moves it along by the clock.
 */
class QuizSession extends Model
{
    use BelongsToChurch;

    public const ACTIVE = ['lobby', 'question', 'reveal'];

    protected $fillable = ['church_id', 'quiz_id', 'host_id', 'pin', 'status', 'current_index', 'question_started_at', 'revealed_at', 'started_at', 'finished_at'];

    /** Millisecond precision so answer speed can be scored fairly. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return [
            'question_started_at' => 'datetime',
            'revealed_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'current_index' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class)->withTrashed();
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(QuizPlayer::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(QuizInvite::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    /** Players ranked by score (ties: more correct, then who joined first). */
    public function leaderboard(int $limit = 10)
    {
        return $this->players()->orderByDesc('score')->orderByDesc('correct_count')->orderBy('id')->take($limit)->get();
    }
}
