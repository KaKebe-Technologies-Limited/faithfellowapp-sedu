<?php

namespace App\Models\Concerns;

use App\Models\Comment;
use App\Models\Reaction;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Content members can react to and comment on (rendered by partials.engagement-bar).
 */
trait HasEngagement
{
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
