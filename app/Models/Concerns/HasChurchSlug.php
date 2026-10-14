<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Generates a slug from the title that is unique within the church.
 */
trait HasChurchSlug
{
    public static function bootHasChurchSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = static::uniqueChurchSlug($model->church_id, $model->title ?? 'item');
            }
        });
    }

    public static function uniqueChurchSlug(?int $churchId, string $title, ?int $ignoreId = null): string
    {
        $base = Str::of($title)->slug()->limit(160, '')->trim('-')->toString() ?: 'item';
        $slug = $base;
        $i = 2;
        $query = fn ($s) => static::withoutGlobalScopes()
            ->when(method_exists(static::class, 'bootSoftDeletes'), fn ($q) => $q->withTrashed())
            ->where('church_id', $churchId)->where('slug', $s)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));

        while ($query($slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
