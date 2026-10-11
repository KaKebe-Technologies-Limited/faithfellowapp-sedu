<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\HasChurchSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SermonSeries extends Model implements HasMedia
{
    use Auditable, BelongsToChurch, HasChurchSlug, InteractsWithMedia, SoftDeletes;

    protected $table = 'sermon_series';

    protected $fillable = ['church_id', 'title', 'slug', 'description', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
    }

    public function sermons(): HasMany
    {
        return $this->hasMany(Sermon::class);
    }
}
