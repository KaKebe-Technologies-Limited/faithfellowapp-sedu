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
        'prayed_count', 'answer_note', 'answered_at',
    ];

    protected function casts(): array
    {
        return ['is_anonymous' => 'boolean', 'chain_enabled' => 'boolean', 'answered_at' => 'datetime'];
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
     *  - public: everyone; church: members of that church; pastor: pastoral staff + author.
     *
     * @param  array<int, int>  $memberChurchIds
     * @param  array<int, int>  $pastoralChurchIds  churches where the viewer has prayer.moderate / care.manage
     */
    public function scopeVisibleTo(Builder $query, ?User $user, array $memberChurchIds = [], array $pastoralChurchIds = []): Builder
    {
        return $query->where('status', '!=', 'hidden')->where(fn ($q) => $q
            ->where('visibility', 'public')
            ->orWhere(fn ($c) => $c->where('visibility', 'church')->whereIn('church_id', $memberChurchIds))
            ->orWhere(fn ($p) => $p->where('visibility', 'pastor')->whereIn('church_id', $pastoralChurchIds))
            ->when($user, fn ($own) => $own->orWhere('user_id', $user->id)));
    }

    public function getAuthorNameAttribute(): string
    {
        return $this->is_anonymous ? __('Anonymous') : ($this->user?->name ?? __('Anonymous'));
    }
}
