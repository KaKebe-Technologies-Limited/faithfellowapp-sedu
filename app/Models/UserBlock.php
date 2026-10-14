<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One member has blocked another: neither can find, write to or see the other any more. */
class UserBlock extends Model
{
    protected $fillable = ['user_id', 'blocked_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    /** Ids of everyone who has blocked this member or been blocked by them. */
    public static function idsAround(int $userId): array
    {
        return static::where('user_id', $userId)->pluck('blocked_id')
            ->merge(static::where('blocked_id', $userId)->pluck('user_id'))->unique()->values()->all();
    }
}
