<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;

class ChurchSocialLink extends Model
{
    use BelongsToChurch;

    public const PLATFORMS = [
        'facebook' => 'bi-facebook', 'instagram' => 'bi-instagram', 'x' => 'bi-twitter-x', 'youtube' => 'bi-youtube',
        'tiktok' => 'bi-tiktok', 'whatsapp' => 'bi-whatsapp', 'telegram' => 'bi-telegram', 'website' => 'bi-globe', 'other' => 'bi-link-45deg',
    ];

    protected $fillable = ['church_id', 'platform', 'url'];

    public function getIconAttribute(): string
    {
        return self::PLATFORMS[$this->platform] ?? 'bi-link-45deg';
    }
}
