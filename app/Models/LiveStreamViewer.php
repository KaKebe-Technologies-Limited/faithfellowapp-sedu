<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;

class LiveStreamViewer extends Model
{
    use BelongsToChurch;

    public $timestamps = false;

    protected $fillable = ['church_id', 'live_stream_id', 'user_id', 'viewer_key', 'audio_only', 'first_seen_at', 'last_seen_at'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'audio_only' => 'boolean'];
    }
}
