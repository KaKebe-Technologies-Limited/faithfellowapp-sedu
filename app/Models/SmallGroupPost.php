<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

/** A message in a group's conversation (study group, cell, fellowship). */
class SmallGroupPost extends Model
{
    use BelongsToChurch, SoftDeletes;

    protected $fillable = ['church_id', 'small_group_id', 'user_id', 'body', 'reply_to_id'];

    private static ?bool $replies = null;

    /** True once the database update that lets a message answer another has been applied (the conversation works without it). */
    public static function repliesReady(): bool
    {
        return self::$replies ??= rescue(fn () => Schema::hasColumn('small_group_posts', 'reply_to_id'), false, false);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id')->withTrashed();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SmallGroup::class, 'small_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
