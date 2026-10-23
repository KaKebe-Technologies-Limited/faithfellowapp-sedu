<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

/**
 * A picture the platform admin controls for the app itself (Super admin → App media): the opening photos that roll when the app starts, the photos behind
 * the welcome screen before sign-in, and the default photos behind the Home banner slider. Where a placement has none, the app uses the photos built
 * into it.
 */
class AppPhoto extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** @var array<string, array{label: string, where: string, tip: string, empty: string, max: int, conversion: string}> */
    public const PLACEMENTS = [
        'opening' => [
            'label' => 'Opening photos',
            'where' => 'The tall rounded photos that roll up and down for a few seconds each time the app is opened.',
            'tip' => 'Portrait photos of people, with the face in the upper half. Six to twelve look best. The app has none of its own: it shows this only when you have added some.',
            'empty' => 'No pictures added: the app opens straight to Home, without the opening photos.',
            'max' => 12,
            'conversion' => 'pill',
        ],
        'welcome' => [
            'label' => 'Welcome screen photos',
            'where' => 'The full-screen photos that fade into each other behind “Welcome to your church family”, before someone signs in.',
            'tip' => 'Portrait photos, with the face in the upper half (the words sit over the lower half). Smiling faces and warm light work best.',
            'empty' => 'No pictures added: the app is using the photos built into it.',
            'max' => 12,
            'conversion' => 'full',
        ],
        'banner' => [
            'label' => 'Home banner slider',
            'where' => 'The default pictures behind the cards of the slider on Home when a church has no pictures of its own, and the banner when there is nothing else to show. A church’s own pictures always come first.',
            'tip' => 'Landscape photos. The title and sentence you add are shown over the picture.',
            'empty' => 'No pictures added: the app is using the photos built into it.',
            'max' => 12,
            'conversion' => 'banner',
        ],
    ];

    protected $fillable = ['placement', 'title', 'subtitle', 'position', 'is_active'];

    private static ?bool $ready = null;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'position' => 'integer'];
    }

    /** True once the database update that adds this table has been applied (the app still opens without it). */
    public static function ready(): bool
    {
        return self::$ready ??= rescue(fn () => Schema::hasTable('app_photos'), false, false);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        $this->addMediaConversion('pill')->fit(Fit::Crop, 480, 923)->format('jpg')->quality(82)->nonQueued()->performOnCollections('photo');
        $this->addMediaConversion('full')->fit(Fit::Crop, 900, 1948)->format('jpg')->quality(80)->nonQueued()->performOnCollections('photo');
        $this->addMediaConversion('banner')->fit(Fit::Crop, 1000, 640)->format('jpg')->quality(80)->nonQueued()->performOnCollections('photo');
    }

    public function scopeOfPlacement(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement);
    }

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /** The picture cut to the size this placement shows it at, or null when the file is missing. */
    public function url(): ?string
    {
        $media = $this->getFirstMedia('photo');
        $conversion = self::PLACEMENTS[$this->placement]['conversion'] ?? 'banner';

        return $media ? ($media->hasGeneratedConversion($conversion) ? $media->getUrl($conversion) : $media->getUrl()) : null;
    }
}
