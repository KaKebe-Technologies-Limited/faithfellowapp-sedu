<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** A collection of hymns, for example "Classic Hymns" in English or a church's hymnal in Luganda. Managed by the super admin. */
class Hymnal extends Model
{
    use SoftDeletes;

    /** Languages a hymnal can be in, with the names members see. */
    public const LANGUAGES = [
        'en' => 'English', 'luo' => 'Luo', 'lg' => 'Luganda', 'sw' => 'Swahili', 'nyn' => 'Runyankole', 'teo' => 'Ateso', 'ach' => 'Acholi', 'lgg' => 'Lugbara', 'fr' => 'French',
    ];

    protected $fillable = ['name', 'slug', 'language', 'denomination', 'description', 'source_name', 'source_url', 'is_published', 'sort', 'hymns_count'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Hymnal $h) => $h->slug = $h->slug ?: static::uniqueSlug($h->name));
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'hymnal';
        $slug = $base;
        for ($i = 2; static::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function hymns(): HasMany
    {
        return $this->hasMany(Hymn::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function languageLabel(): string
    {
        return self::LANGUAGES[$this->language] ?? strtoupper($this->language);
    }

    public function refreshCount(): void
    {
        $this->forceFill(['hymns_count' => $this->hymns()->where('status', 'published')->count()])->saveQuietly();
    }
}
