<?php

namespace App\Services;

use App\Models\Church;
use App\Support\ChurchArtwork;
use Illuminate\Support\Carbon;

/**
 * Gives a church a clean default logo, banner and home-slider photos, in its
 * brand colour, when it doesn't have its own yet (demo data, new churches).
 */
class ChurchArtworkService
{
    /** Theme + caption builders for the five default slides. */
    protected function slideSpecs(Church $church): array
    {
        $service = $church->serviceTimes()->orderBy('day_of_week')->orderBy('start_time')->get()
            ->first(fn ($s) => $s->day_of_week === 0) ?? $church->serviceTimes()->first();
        $when = $service ? __(':day :time', ['day' => $service->day_name, 'time' => Carbon::parse($service->start_time)->format('g:i A')]) : __('Sundays');

        return [
            ['worship', __('Worship with us'), __(':when · :city — come as you are', ['when' => $when, 'city' => $church->city ?: __('our church')])],
            ['sunrise', __('Welcome home'), $church->tagline ?: __('A family of believers who love God and people')],
            ['cross', __('Grow in the Word'), __('Join a reading plan with your reading partners')],
            ['bokeh', __('Night of prayer'), __('Fridays from 9:00 PM — bring a friend')],
            ['waves', __('Youth Camp :year', ['year' => now()->year]), __('Register in Events — spaces are limited')],
        ];
    }

    public function apply(Church $church, bool $force = false, ?string $cacheDir = null): void
    {
        $church->loadMissing('media');
        $hex = $church->brand_color ?: '#BC2649';
        $dir = ($cacheDir ?? storage_path('app/artwork')).'/'.$church->slug.'-'.ltrim($hex, '#');
        $seed = crc32($church->slug) % 1000;

        if ($force || ! $church->getFirstMedia('logo')) {
            $church->clearMediaCollection('logo');
            $path = $this->cached($dir.'/logo.png', fn ($p) => ChurchArtwork::logo($p, $church->name, $hex, $seed % 5));
            $church->addMedia($path)->preservingOriginal()->usingFileName('logo-'.$church->slug.'.png')->toMediaCollection('logo');
        }

        if ($force || ! $church->getFirstMedia('cover')) {
            $church->clearMediaCollection('cover');
            $path = $this->cached($dir.'/banner.jpg', fn ($p) => ChurchArtwork::banner($p, $hex, $seed));
            $church->addMedia($path)->preservingOriginal()->usingFileName('cover-'.$church->slug.'.jpg')->toMediaCollection('cover');
        }

        if ($force || $church->slides()->count() < (int) config('faithfellows.limits.min_slides', 4)) {
            $church->clearMediaCollection('slides');
            $specs = $this->slideSpecs($church);
            // Rotate the order so each church opens with a different scene.
            $offset = $seed % count($specs);
            $specs = array_merge(array_slice($specs, $offset), array_slice($specs, 0, $offset));

            foreach ($specs as $i => [$theme, $title, $subtitle]) {
                $path = $this->cached($dir."/slide-{$theme}.jpg", fn ($p) => ChurchArtwork::slide($p, $theme, $hex, $seed + $i));
                $church->addMedia($path)->preservingOriginal()->usingFileName("slide-{$i}-{$church->slug}.jpg")
                    ->withCustomProperties(['title' => $title, 'subtitle' => $subtitle])
                    ->toMediaCollection('slides');
            }
        }

        $church->unsetRelation('media');
    }

    protected function cached(string $path, callable $make): string
    {
        if (! is_file($path)) {
            $make($path);
        }

        return $path;
    }
}
