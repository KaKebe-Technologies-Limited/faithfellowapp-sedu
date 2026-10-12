<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingCircleMember extends Model
{
    public $timestamps = false;

    protected $fillable = ['reading_circle_id', 'user_id', 'role', 'joined_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(ReadingCircle::class, 'reading_circle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
