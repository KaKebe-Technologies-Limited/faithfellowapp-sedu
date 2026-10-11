<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentView extends Model
{
    use BelongsToChurch;

    public const UPDATED_AT = null;

    protected $fillable = ['church_id', 'viewable_type', 'viewable_id', 'user_id', 'action', 'seconds'];

    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }
}
