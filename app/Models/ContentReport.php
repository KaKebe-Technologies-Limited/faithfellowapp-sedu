<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A member's report about a post, comment, prayer request or testimony.
 * Church moderators and platform moderators work the queue.
 */
class ContentReport extends Model
{
    public const REASONS = ['spam' => 'Spam or scam', 'abuse' => 'Harassment or abuse', 'inappropriate' => 'Inappropriate content', 'false_information' => 'False information', 'other' => 'Something else'];

    /** Types members can report, keyed as in the report URL. */
    public const TYPES = [
        'post' => Post::class,
        'comment' => Comment::class,
        'podcast_comment' => PodcastComment::class,
        'prayer' => PrayerRequest::class,
        'testimony' => Testimony::class,
        'live_chat' => LiveChatMessage::class,
    ];

    protected $fillable = ['church_id', 'reportable_type', 'reportable_id', 'user_id', 'reason', 'details', 'status', 'handled_by', 'handled_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScopes();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
