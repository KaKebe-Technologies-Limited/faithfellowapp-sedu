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
        'en' => 'English', 'lg' => 'Luganda', 'laj' => 'Lango', 'ach' => 'Acholi', 'luo' => 'Luo', 'teo' => 'Ateso', 'nyn' => 'Runyankole', 'cgg' => 'Rukiga', 'xog' => 'Lusoga',
        'myx' => 'Lumasaaba', 'alz' => 'Alur', 'lgg' => 'Lugbara', 'kdj' => 'Karamojong', 'sw' => 'Swahili', 'fr' => 'French',
    ];

    protected $fillable = ['name', 'slug', 'language', 'denomination', 'description', 'sections', 'source_name', 'source_url', 'is_published', 'sort', 'hymns_count'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sections' => 'array'];
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

    /**
     * The parts of the book in order, each with what it means and how many hymns it holds: [{ name, meaning, count }].
     *
     * @return list<array{name: string, meaning: string|null, count: int}>
     */
    public function sectionList(): array
    {
        $counts = $this->hymns()->where('status', 'published')->whereNotNull('section')->selectRaw('section, count(*) as n')->groupBy('section')->pluck('n', 'section');

        return collect($this->sections ?? [])->map(fn ($s) => ['name' => $s['name'], 'meaning' => $s['meaning'] ?? null, 'count' => (int) ($counts[$s['name']] ?? 0)])
            ->filter(fn ($s) => $s['count'] > 0)->values()->all();
    }

    /** "Name | meaning" per line (as the super admin types them) → [{ name, meaning }]. */
    public static function parseSections(?string $text): ?array
    {
        $rows = collect(preg_split('/\R/', (string) $text))->map(fn ($l) => array_map('trim', explode('|', $l, 2)))->filter(fn ($p) => $p[0] !== '')
            ->map(fn ($p) => ['name' => $p[0], 'meaning' => ($p[1] ?? '') !== '' ? $p[1] : null])->unique('name')->values()->all();

        return $rows ?: null;
    }

    public function sectionsForEditing(): string
    {
        return collect($this->sections ?? [])->map(fn ($s) => $s['name'].(($s['meaning'] ?? null) ? ' | '.$s['meaning'] : ''))->implode("\n");
    }

    public function refreshCount(): void
    {
        $this->forceFill(['hymns_count' => $this->hymns()->where('status', 'published')->count()])->saveQuietly();
    }
}
