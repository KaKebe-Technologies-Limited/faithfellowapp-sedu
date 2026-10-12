<?php

namespace App\Services\Bible;

interface BibleProvider
{
    /**
     * One chapter as an ordered list of verses.
     *
     * @param  array<string, mixed>  $translation  config entry (key, provider id…)
     * @return array<int, array{v: int, t: string}>
     *
     * @throws BibleUnavailable
     */
    public function chapter(array $translation, string $book, int $chapter): array;
}
