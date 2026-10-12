<?php

namespace App\Services\Bible;

use Illuminate\Support\Facades\Http;

/**
 * API.Bible (scripture.api.bible) — needs a free API key and gives access to
 * licensed translations in East African languages (e.g. Kiswahili, Luganda).
 * Each translation in config/faithfellows.php names its API.Bible "bible id".
 */
class ApiBibleProvider implements BibleProvider
{
    public function chapter(array $translation, string $book, int $chapter): array
    {
        $key = config('services.bible.api_bible_key');
        if (! $key || empty($translation['id'])) {
            throw new BibleUnavailable('API.Bible is not configured.');
        }

        try {
            $response = Http::acceptJson()->timeout(12)->withHeaders(['api-key' => $key])
                ->get("https://api.scripture.api.bible/v1/bibles/{$translation['id']}/chapters/{$book}.{$chapter}", [
                    'content-type' => 'text',
                    'include-notes' => 'false',
                    'include-titles' => 'false',
                    'include-chapter-numbers' => 'false',
                    'include-verse-numbers' => 'true',
                ]);
        } catch (\Throwable $e) {
            throw new BibleUnavailable($e->getMessage(), 0, $e);
        }

        $content = (string) $response->json('data.content');
        if (! $response->successful() || $content === '') {
            throw new BibleUnavailable('API.Bible responded '.$response->status());
        }

        // Plain text arrives as "[1] In the beginning… [2] And the earth…".
        $parts = preg_split('/\[(\d+)\]/', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $verses = [];
        for ($i = 0; $i < count($parts) - 1; $i++) {
            if (ctype_digit(trim($parts[$i]))) {
                $verses[] = ['v' => (int) $parts[$i], 't' => BibleApiComProvider::clean($parts[$i + 1])];
                $i++;
            }
        }

        return $verses;
    }
}
