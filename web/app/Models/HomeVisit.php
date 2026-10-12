<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeVisit extends Model
{
    use BelongsToChurch;

    protected $fillable = ['church_id', 'user_id', 'visitor_id', 'scheduled_at', 'purpose', 'status', 'notes'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'notes' => 'encrypted'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_id');
    }
}
