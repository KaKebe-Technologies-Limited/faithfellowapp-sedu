<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventQuestion extends Model
{
    use BelongsToChurch;

    protected $fillable = ['church_id', 'event_id', 'user_id', 'body', 'upvotes', 'is_answered', 'is_hidden'];

    protected function casts(): array
    {
        return ['is_answered' => 'boolean', 'is_hidden' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
