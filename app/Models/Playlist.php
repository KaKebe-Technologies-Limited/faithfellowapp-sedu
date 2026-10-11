<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Playlist extends Model
{
    use BelongsToChurch;

    protected $fillable = ['church_id', 'title', 'description', 'is_public'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function sermons(): BelongsToMany
    {
        return $this->belongsToMany(Sermon::class)->withPivot('position')->orderByPivot('position');
    }
}
