<?php

namespace App\Services;

use App\Models\AppPhoto;

/**
 * What the app is told about its own pictures: the lists the platform admin set up (Super admin → App media). An empty list means "use the photos built
 * into the app". `version` changes whenever anything changes, so the app knows to fetch the pictures again.
 */
class AppMedia
{
    /** @return array{opening: list<array<string, mixed>>, welcome: list<array<string, mixed>>, banner: list<array<string, mixed>>, version: string} */
    public static function payload(): array
    {
        $out = ['opening' => [], 'welcome' => [], 'banner' => []];
        if (! AppPhoto::ready()) {
            return $out + ['version' => '0'];
        }

        AppPhoto::with('media')->where('is_active', true)->whereIn('placement', array_keys(AppPhoto::PLACEMENTS))->inOrder()->get()->each(function (AppPhoto $photo) use (&$out) {
            if ($url = $photo->url()) {
                $out[$photo->placement][] = ['id' => $photo->id, 'url' => $url] + ($photo->placement === 'banner' ? ['title' => $photo->title, 'subtitle' => $photo->subtitle] : []);
            }
        });

        return $out + ['version' => substr(md5(json_encode($out)), 0, 12)];
    }
}
