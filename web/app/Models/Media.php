<?php

namespace App\Models;

use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * Media rows carry church_id so storage quotas and bandwidth analytics can
 * be computed per church.
 */
class Media extends BaseMedia
{
    protected static function booted(): void
    {
        static::creating(function (Media $media) {
            if ($media->church_id) {
                return;
            }

            $owner = $media->model;
            $media->church_id = match (true) {
                $owner instanceof Church => $owner->id,
                isset($owner?->church_id) => $owner->church_id,
                default => null,
            };
        });
    }
}
