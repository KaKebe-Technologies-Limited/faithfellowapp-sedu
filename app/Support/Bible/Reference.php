<?php

namespace App\Support\Bible;

/**
 * Parses Bible references into chapter segments.
 *
 *   "Matthew 3-5"            → MAT 3, MAT 4, MAT 5
 *   "Matt 5:1-12"            → MAT 5 (verses 1–12)
 *   "John 3:16"              → JHN 3 (verse 16)
 *   "Genesis 1–2; Psalm 1"   → GEN 1, GEN 2, PSA 1
 *   "1 Cor 13"               → 1CO 13
 *   "Romans 8:28-9:5"        → ROM 8 (28–end), ROM 9 (1–5)
 *   "Obadiah"                → OBA 1 (whole book when it is short)
 */
final class Reference
{
    /**
     * @param  array<int, array{book: string, chapter: int, from: ?int, to: ?int}>  $segments
     */
    public function __construct(public readonly string $input, public readonly array $segments) {}

    public static function parse(?string $text): ?self
    {
        $text = trim(str_replace(['–', '—', '‑'], '-', (string) $text));
        if ($text === '') {
            return null;
        }

        $segments = [];
        $lastBook = null;
        foreach (preg_split('/\s*[;,]\s*(?=(?:[1-3]\s*)?[A-Za-z])|\s*;\s*/', $text) as $part) {
            if (! preg_match('/^\s*((?:[1-3]|i{1,3})?\s*[A-Za-z][A-Za-z.\s’\']*?)?\s*(\d+)?(?::(\d+))?(?:\s*-\s*(\d+)(?::(\d+))?)?\s*$/i', $part, $m)) {
                continue;
            }

            $book = isset($m[1]) && trim($m[1]) !== '' ? Books::find($m[1]) : $lastBook;
            if (! $book) {
                continue;
            }
            $lastBook = $book;

            $chapter = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null;
            $verse = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;
            $endA = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : null;
            $endB = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : null;
            $max = Books::chapters($book);

            if ($chapter === null) {
                // A whole book: only expand short books (Jude, Obadiah, Ruth…).
                if ($max > 5) {
                    $segments[] = ['book' => $book, 'chapter' => 1, 'from' => null, 'to' => null];
                } else {
                    foreach (range(1, $max) as $c) {
                        $segments[] = ['book' => $book, 'chapter' => $c, 'from' => null, 'to' => null];
                    }
                }

                continue;
            }

            $chapter = max(1, min($max, $chapter));

            if ($verse === null) {
                // "Matthew 3" or "Matthew 3-5" (chapter range).
                $last = $endA ? max($chapter, min($max, $endA)) : $chapter;
                foreach (range($chapter, $last) as $c) {
                    $segments[] = ['book' => $book, 'chapter' => $c, 'from' => null, 'to' => null];
                }
            } elseif ($endB !== null) {
                // "Romans 8:28-9:5" (crosses chapters).
                $endChapter = max($chapter, min($max, $endA));
                foreach (range($chapter, $endChapter) as $c) {
                    $segments[] = ['book' => $book, 'chapter' => $c, 'from' => $c === $chapter ? $verse : 1, 'to' => $c === $endChapter ? $endB : null];
                }
            } else {
                // "John 3:16" or "Matt 5:1-12".
                $segments[] = ['book' => $book, 'chapter' => $chapter, 'from' => $verse, 'to' => $endA ?? $verse];
            }
        }

        return $segments ? new self($text, array_slice($segments, 0, 15)) : null;
    }

    /**
     * Each segment's canonical label: "Matthew 3", "John 3:16", "Matthew 5:1–12".
     */
    public static function label(array $segment): string
    {
        $label = Books::name($segment['book']).' '.$segment['chapter'];
        if ($segment['from']) {
            $label .= ':'.$segment['from'].($segment['to'] && $segment['to'] !== $segment['from'] ? '–'.$segment['to'] : ($segment['to'] === null ? '–' : ''));
        }

        return $label;
    }

    /** Short canonical label for the whole reference ("Matthew 3–5"). */
    public function title(): string
    {
        return collect($this->segments)->groupBy('book')->map(function ($segments) {
            $whole = $segments->every(fn ($s) => $s['from'] === null);
            $first = $segments->first();
            $last = $segments->last();
            $contiguous = $segments->count() - 1 === $last['chapter'] - $first['chapter'];

            if ($whole && $contiguous && $segments->count() > 1) {
                return Books::name($first['book']).' '.$first['chapter'].'–'.$last['chapter'];
            }

            return $segments->map(fn ($s) => self::label($s))->implode('; ');
        })->implode('; ');
    }
}
