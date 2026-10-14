<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reaction extends Model
{
    use BelongsToChurch;

    public const TYPES = ['amen' => '🙏', 'love' => '❤️', 'pray' => '🕊️', 'celebrate' => '🎉'];

    protected $fillable = ['church_id', 'reactable_type', 'reactable_id', 'user_id', 'type'];

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }
}
