<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/** A private message between two connected members. */
class DirectMessage extends Model
{
    protected $fillable = ['sender_id', 'recipient_id', 'body', 'read_at', 'reply_to_id'];

    private static ?bool $replies = null;

    /** True once the database update that lets a message answer another has been applied (chat works without it). */
    public static function repliesReady(): bool
    {
        return self::$replies ??= rescue(fn () => Schema::hasColumn('direct_messages', 'reply_to_id'), false, false);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** Messages exchanged by two members, in either direction. */
    public function scopeBetween(Builder $query, int $a, int $b): Builder
    {
        return $query->where(fn ($q) => $q->where(fn ($w) => $w->where('sender_id', $a)->where('recipient_id', $b))
            ->orWhere(fn ($w) => $w->where('sender_id', $b)->where('recipient_id', $a)));
    }
}
