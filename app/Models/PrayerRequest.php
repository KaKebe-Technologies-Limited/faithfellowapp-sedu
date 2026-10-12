<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\HasEngagement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrayerRequest extends Model
{
    use BelongsToChurch, HasEngagement, SoftDeletes;

    protected $fillable = [
        'church_id', 'user_id', 'title', 'body', 'visibility', 'is_anonymous', 'status', 'chain_enabled',
        'prayed_count', 'answer_note', 'answered_at', 'approved_by', 'approved_at', 'pastor_response', 'responded_by', 'responded_at',
    ];

    /** Statuses everyone (allowed by visibility) can see on the wall. */
    public const SHARED = ['open', 'answered'];

    protected $hidden = ['pastor_response'];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean', 'chain_enabled' => 'boolean', 'answered_at' => 'datetime',
            'approved_at' => 'datetime', 'responded_at' => 'datetime', 'pastor_response' => 'encrypted',
        ];
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function isShared(): bool
    {
        return in_array($this->status, self::SHARED, true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(PrayerInteraction::class);
    }

    /**
     * Requests a viewer may see.
     *  - Approved (open/answered) public requests: everyone; church requests: members of that church.
     *  - Pending, declined and pastor-only requests: the author and the church's pastoral team.
     *
     * @param  array<int, int>  $memberChurchIds
     * @param  array<int, int>  $pastoralChurchIds  churches where the viewer has prayer.moderate / care.manage
     */
    public function scopeVisibleTo(Builder $query, ?User $user, array $memberChurchIds = [], array $pastoralChurchIds = []): Builder
    {
        return $query->where(fn ($q) => $q
            ->where(fn ($shared) => $shared->whereIn('status', self::SHARED)->where(fn ($v) => $v
                ->where('visibility', 'public')
                ->orWhere(fn ($c) => $c->where('visibility', 'church')->whereIn('church_id', $memberChurchIds))))
            ->orWhere(fn ($p) => $p->whereIn('church_id', $pastoralChurchIds)->where('status', '!=', 'hidden'))
            ->when($user, fn ($own) => $own->orWhere('user_id', $user->id)));
    }

    public function getAuthorNameAttribute(): string
    {
        return $this->is_anonymous ? __('Anonymous') : ($this->user?->name ?? __('Anonymous'));
    }
}
