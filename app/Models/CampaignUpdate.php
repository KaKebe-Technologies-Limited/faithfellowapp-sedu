<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CampaignUpdate extends Model implements HasMedia
{
    use BelongsToChurch, InteractsWithMedia;

    protected $fillable = ['church_id', 'campaign_id', 'user_id', 'title', 'body', 'is_testimony', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'is_testimony' => 'boolean'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
