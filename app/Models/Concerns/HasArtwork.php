<?php

namespace App\Models\Concerns;

use App\Support\ContentArtwork;

/**
 * `picture_url`: the church's own photo when it has uploaded one, otherwise a generated cover (ContentArtwork)
 * that fits the item, so the app and the website never show an empty grey box.
 */
trait HasArtwork
{
    /** 'event', 'sermon', 'devotional' or 'campaign'. */
    abstract public function artworkKind(): string;

    /** The uploaded photo's URL, or null when there is none. */
    abstract public function uploadedPicture(): ?string;

    public function getPictureUrlAttribute(): string
    {
        return $this->uploadedPicture() ?: ContentArtwork::url($this->artworkKind(), $this);
    }
}
