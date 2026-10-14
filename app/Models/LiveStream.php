<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiveStream extends Model
{
    use Auditable, BelongsToChurch, HasFactory, SoftDeletes;

    protected $fillable = [
        'church_id', 'branch_id', 'user_id', 'event_id', 'title', 'description', 'scheduled_at', 'started_at', 'ended_at',
        'status', 'source_type', 'stream_key', 'stream_key_hash', 'external_url', 'chat_enabled', 'audio_only_available',
        'peak_viewers', 'total_viewers', 'recording_url', 'sermon_id', 'notified_at',
    ];

    protected $hidden = ['stream_key', 'stream_key_hash'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'notified_at' => 'datetime',
            'chat_enabled' => 'boolean',
            'audio_only_available' => 'boolean',
            'stream_key' => 'encrypted',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sermon(): BelongsTo
    {
        return $this->belongsTo(Sermon::class);
    }

    public function viewers(): HasMany
    {
        return $this->hasMany(LiveStreamViewer::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(LiveChatMessage::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'live');
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function durationMinutes(): int
    {
        if (! $this->started_at) {
            return 0;
        }

        return (int) $this->started_at->diffInMinutes($this->ended_at ?? now());
    }

    public function auditLabel(): ?string
    {
        return $this->title;
    }
}
