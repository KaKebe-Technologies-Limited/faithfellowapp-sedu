<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Confidential: topic and counselor notes are encrypted at rest and never
 * shown to other staff, audit logs or exports.
 */
class CounselingAppointment extends Model
{
    use BelongsToChurch;

    protected $fillable = ['church_id', 'user_id', 'counselor_id', 'preferred_at', 'scheduled_at', 'duration_minutes', 'mode', 'topic', 'counselor_notes', 'status'];

    protected $hidden = ['topic', 'counselor_notes'];

    protected function casts(): array
    {
        return [
            'preferred_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'topic' => 'encrypted',
            'counselor_notes' => 'encrypted',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }
}
