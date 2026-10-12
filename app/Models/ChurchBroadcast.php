<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A message composed by church staff and sent to an audience over the
 * channels they picked (in-app always, plus email / push / SMS / WhatsApp).
 */
class ChurchBroadcast extends Model
{
    use Auditable, BelongsToChurch;

    public const AUDIENCES = ['members' => 'All members', 'staff' => 'Staff only', 'branch' => 'A branch', 'group' => 'A small group', 'stage' => 'By membership stage'];

    protected $fillable = [
        'church_id', 'user_id', 'title', 'body', 'url', 'audience', 'audience_options', 'channels', 'category',
        'is_urgent', 'status', 'scheduled_at', 'sent_at', 'recipients_count',
    ];

    protected function casts(): array
    {
        return [
            'audience_options' => 'array',
            'channels' => 'array',
            'is_urgent' => 'boolean',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function audienceLabel(): string
    {
        $options = $this->audience_options ?? [];

        return match ($this->audience) {
            'stage' => __('Stage: :s', ['s' => collect($options['stages'] ?? [])->map(fn ($s) => __(Str::headline($s)))->implode(', ')]),
            default => __(self::AUDIENCES[$this->audience] ?? $this->audience),
        };
    }
}
