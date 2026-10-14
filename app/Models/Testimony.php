<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\HasEngagement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Testimony extends Model
{
    use BelongsToChurch, HasEngagement, SoftDeletes;

    protected $fillable = ['church_id', 'user_id', 'prayer_request_id', 'title', 'body', 'is_anonymous', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['is_anonymous' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function prayerRequest(): BelongsTo
    {
        return $this->belongsTo(PrayerRequest::class);
    }

    public function getAuthorNameAttribute(): string
    {
        return $this->is_anonymous ? __('Anonymous') : ($this->user?->name ?? __('Anonymous'));
    }
}
