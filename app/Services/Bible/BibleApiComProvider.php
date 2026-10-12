<?php

namespace App\Services\Bible;

use Illuminate\Support\Facades\Http;

/**
 * bible-api.com — free, no key, public-domain translations (KJV, WEB, BBE, ASV…).
 * Rate limit: about 15 requests / 30 s per IP, which the chapter cache absorbs.
 */
class BibleApiComProvider implements BibleProvider
{
    public function chapter(array $translation, string $book, int $chapter): array
    {
        $id = $translation['id'] ?? $translation['key'];

        try {
            $response = Http::acceptJson()->timeout(12)->retry(2, 800, throw: false)
                ->get("https://bible-api.com/data/{$id}/{$book}/{$chapter}");
        } catch (\Throwable $e) {
            throw new BibleUnavailable($e->getMessage(), 0, $e);
        }

        if (! $response->successful() || ! is_array($response->json('verses'))) {
            throw new BibleUnavailable('bible-api.com responded '.$response->status());
        }

        return collect($response->json('verses'))
            ->map(fn ($v) => ['v' => (int) $v['verse'], 't' => self::clean((string) $v['text'])])
            ->filter(fn ($v) => $v['t'] !== '')
            ->values()->all();
    }

    public static function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace(['¶', "\u{00B6}"], '', $text)));
    }
}
