<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

/**
 * A listener's comment under a podcast episode or under a podcast channel of the radio (the Podcast radio, or a church's channel). Reported comments are
 * held back ("flagged") until a moderator decides; a podcast's maker, a church's staff for their own channel and the super admin can remove them.
 * Before the database update that adds the table, nobody can comment and the lists are simply empty (see {@see ready()}).
 */
class PodcastComment extends Model
{
    use SoftDeletes;

    protected $fillable = ['commentable_type', 'commentable_id', 'user_id', 'body', 'status'];

    private static ?bool $ready = null;

    public static function ready(): bool
    {
        return self::$ready ??= rescue(fn () => Schema::hasTable('podcast_comments'), false, false);
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'visible');
    }
}
