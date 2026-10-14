<?php

namespace App\Services\Bible;

use App\Support\Bible\Books;
use Illuminate\Support\Facades\Http;

/**
 * The English Standard Version through Crossway's ESV API (api.esv.org), which needs a free API key (ESV_API_KEY).
 * The ESV is licensed text: Crossway's terms do not allow keeping it in our database or on phones in bulk, so BibleService
 * never stores chapters of a translation whose config says 'offline' => false; every read comes from the API.
 */
class EsvApiProvider implements BibleProvider
{
    public const COPYRIGHT = 'Scripture quotations are from the ESV® Bible (The Holy Bible, English Standard Version®), © 2001 by Crossway, a publishing ministry of Good News Publishers. Used by permission. All rights reserved.';

    public function chapter(array $translation, string $book, int $chapter): array
    {
        $key = config('services.bible.esv_key');
        if (! $key) {
            throw new BibleUnavailable('The ESV is not configured.');
        }

        // The ESV search knows the book as "Psalm", not "Psalms".
        $name = $book === 'PSA' ? 'Psalm' : Books::name($book);

        // The home screen lowers the shared timeout so a slow Bible service never holds the page up.
        $timeout = BibleApiComProvider::$timeout;

        try {
            $response = Http::acceptJson()->timeout($timeout ?? 12)->retry($timeout ? 0 : 1, 600, throw: false)->withHeaders(['Authorization' => 'Token '.$key])
                ->get('https://api.esv.org/v3/passage/text/', [
                    'q' => "{$name} {$chapter}",
                    'include-passage-references' => 'false',
                    'include-verse-numbers' => 'true',
                    'include-first-verse-numbers' => 'true',
                    'include-footnotes' => 'false',
                    'include-footnote-body' => 'false',
                    'include-headings' => 'false',
                    'include-short-copyright' => 'false',
                    'include-copyright' => 'false',
                    'include-selahs' => 'true',
                    'include-passage-horizontal-lines' => 'false',
                    'include-heading-horizontal-lines' => 'false',
                    'indent-poetry' => 'false',
                    'indent-paragraphs' => 0,
                    'indent-declares' => 0,
                    'indent-psalm-doxology' => 0,
                    'line-length' => 0,
                ]);
        } catch (\Throwable $e) {
            throw new BibleUnavailable($e->getMessage(), 0, $e);
        }

        $text = (string) ($response->json('passages.0') ?? '');
        if (! $response->successful() || $text === '') {
            throw new BibleUnavailable('The ESV API responded '.$response->status());
        }

        return self::verses($text);
    }

    /**
     * The API returns "[1] In the beginning… [2] The earth was…" for a chapter.
     *
     * @return array<int, array{v: int, t: string}>
     */
    public static function verses(string $text): array
    {
        $parts = preg_split('/\[(\d+)\]/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $verses = [];
        for ($i = 0; $i < count($parts) - 1; $i++) {
            if (ctype_digit(trim($parts[$i]))) {
                $clean = BibleApiComProvider::clean($parts[$i + 1]);
                if ($clean !== '') {
                    $verses[] = ['v' => (int) $parts[$i], 't' => $clean];
                }
                $i++;
            }
        }

        return $verses;
    }
}
