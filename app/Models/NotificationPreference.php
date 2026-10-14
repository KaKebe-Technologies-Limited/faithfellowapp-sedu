<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    /** Channels used when a user has not set a preference. */
    public const DEFAULT_CHANNELS = [
        'live' => ['database', 'webpush', 'mail'],
        'sermon' => ['database', 'webpush'],
        'event' => ['database', 'webpush', 'mail'],
        'giving' => ['database', 'mail'],
        'announcement' => ['database', 'webpush', 'mail'],
        'update' => ['database', 'webpush'],
        'prayer' => ['database', 'webpush'],
        'devotional' => ['database', 'webpush'],
        'reading' => ['database', 'webpush'],
        'games' => ['database', 'webpush'],
        'social' => ['database', 'webpush'],
    ];

    protected $fillable = ['user_id', 'church_id', 'category', 'channels', 'enabled'];

    protected function casts(): array
    {
        return ['channels' => 'array', 'enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
