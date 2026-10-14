<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SermonTranscript extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'church_id', 'sermon_id', 'language', 'body', 'summary', 'key_verses', 'discussion_questions',
        'source', 'status', 'provider', 'error',
    ];

    protected function casts(): array
    {
        return ['key_verses' => 'array', 'discussion_questions' => 'array'];
    }

    public function sermon(): BelongsTo
    {
        return $this->belongsTo(Sermon::class);
    }
}
