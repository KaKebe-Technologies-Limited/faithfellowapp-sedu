<?php

namespace App\Services\Bible;

use App\Models\BibleChapter;
use App\Models\User;
use App\Support\Bible\Books;
use App\Support\Bible\Reference;

/**
 * Bible text for the reader, reading plans, devotionals and sermons.
 * Chapters are fetched from the configured provider once and then served
 * from the bible_chapters table.
 */
class BibleService
{
    /**
     * Translations offered to readers (only those whose provider is usable).
     *
     * @return array<string, array{key: string, name: string, short: string, language: string, provider: string, id?: string}>
     */
    public function translations(): array
    {
        return collect(config('faithfellows.bible.translations'))
            ->map(fn ($t, $key) => [...$t, 'key' => $key])
            ->filter(fn ($t) => $t['provider'] !== 'api_bible' || filled(config('services.bible.api_bible_key')))
            ->all();
    }

    public function defaultTranslation(?User $user = null): string
    {
        $wanted = $user?->bible_translation ?: session('bible_translation') ?: config('faithfellows.bible.default', 'kjv');

        return array_key_exists($wanted, $this->translations()) ? $wanted : 'kjv';
    }

    /**
     * @return array<int, array{v: int, t: string}>
     *
     * @throws BibleUnavailable
     */
    public function chapter(string $translation, string $book, int $chapter): array
    {
        if (! Books::exists($book) || $chapter < 1 || $chapter > Books::chapters($book)) {
            throw new BibleUnavailable("Unknown chapter {$book} {$chapter}");
        }

        $cached = BibleChapter::where('translation', $translation)->where('book', $book)->where('chapter', $chapter)->first();
        if ($cached) {
            return $cached->verses;
        }

        $config = $this->translations()[$translation] ?? throw new BibleUnavailable("Unknown translation {$translation}");
        $verses = $this->provider($config['provider'])->chapter($config, $book, $chapter);
        if ($verses === []) {
            throw new BibleUnavailable("Empty chapter {$book} {$chapter}");
        }

        BibleChapter::updateOrCreate(['translation' => $translation, 'book' => $book, 'chapter' => $chapter], ['verses' => $verses]);

        return $verses;
    }

    /**
     * Resolve a reference ("Matthew 3–5") into readable sections.
     *
     * @return array{title: string, sections: array<int, array{label: string, book: string, chapter: int, verses: array<int, array{v: int, t: string}>, error: ?string}>}|null
     */
    public function passage(string $reference, string $translation): ?array
    {
        $ref = Reference::parse($reference);
        if (! $ref) {
            return null;
        }

        $sections = [];
        foreach ($ref->segments as $segment) {
            try {
                $verses = collect($this->chapter($translation, $segment['book'], $segment['chapter']))
                    ->filter(fn ($v) => (! $segment['from'] || $v['v'] >= $segment['from']) && (! $segment['to'] || $v['v'] <= $segment['to']))
                    ->values()->all();
                $error = null;
            } catch (BibleUnavailable) {
                $verses = [];
                $error = __('We could not load this chapter right now. Check your connection and try again.');
            }

            $sections[] = ['label' => Reference::label($segment), 'book' => $segment['book'], 'chapter' => $segment['chapter'], 'verses' => $verses, 'error' => $error];
        }

        return ['title' => $ref->title(), 'sections' => $sections];
    }

    protected function provider(string $name): BibleProvider
    {
        return match ($name) {
            'api_bible' => app(ApiBibleProvider::class),
            default => app(BibleApiComProvider::class),
        };
    }
}
