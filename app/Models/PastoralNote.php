<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Confidential notes about a member. Encrypted at rest. Private notes are
 * readable only by their author; shared notes by staff with care.manage.
 */
class PastoralNote extends Model
{
    use BelongsToChurch;

    protected $fillable = ['church_id', 'user_id', 'author_id', 'body', 'is_private'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'is_private' => 'boolean'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeReadableBy(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->where('is_private', false)->orWhere('author_id', $user->id));
    }
}
