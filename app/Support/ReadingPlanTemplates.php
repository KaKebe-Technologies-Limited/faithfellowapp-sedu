<?php

namespace App\Support;

/**
 * Built-in reading plans churches can import with one click.
 */
class ReadingPlanTemplates
{
    /** Bible books with chapter counts (Protestant canon). */
    public const BOOKS = [
        'Genesis' => 50, 'Exodus' => 40, 'Leviticus' => 27, 'Numbers' => 36, 'Deuteronomy' => 34, 'Joshua' => 24, 'Judges' => 21,
        'Ruth' => 4, '1 Samuel' => 31, '2 Samuel' => 24, '1 Kings' => 22, '2 Kings' => 25, '1 Chronicles' => 29, '2 Chronicles' => 36,
        'Ezra' => 10, 'Nehemiah' => 13, 'Esther' => 10, 'Job' => 42, 'Psalms' => 150, 'Proverbs' => 31, 'Ecclesiastes' => 12,
        'Song of Songs' => 8, 'Isaiah' => 66, 'Jeremiah' => 52, 'Lamentations' => 5, 'Ezekiel' => 48, 'Daniel' => 12, 'Hosea' => 14,
        'Joel' => 3, 'Amos' => 9, 'Obadiah' => 1, 'Jonah' => 4, 'Micah' => 7, 'Nahum' => 3, 'Habakkuk' => 3, 'Zephaniah' => 3,
        'Haggai' => 2, 'Zechariah' => 14, 'Malachi' => 4, 'Matthew' => 28, 'Mark' => 16, 'Luke' => 24, 'John' => 21, 'Acts' => 28,
        'Romans' => 16, '1 Corinthians' => 16, '2 Corinthians' => 13, 'Galatians' => 6, 'Ephesians' => 6, 'Philippians' => 4,
        'Colossians' => 4, '1 Thessalonians' => 5, '2 Thessalonians' => 3, '1 Timothy' => 6, '2 Timothy' => 4, 'Titus' => 3,
        'Philemon' => 1, 'Hebrews' => 13, 'James' => 5, '1 Peter' => 5, '2 Peter' => 3, '1 John' => 5, '2 John' => 1, '3 John' => 1,
        'Jude' => 1, 'Revelation' => 22,
    ];

    /**
     * @return array<string, array{title: string, description: string, entries: array<int, array<string, mixed>>}>
     */
    public static function all(): array
    {
        return [
            'bible-in-a-year' => [
                'title' => 'Bible in a Year',
                'description' => 'Read through the whole Bible in 365 days, about 3–4 chapters a day.',
                'entries' => self::spread(array_keys(self::BOOKS), 365),
            ],
            'new-testament-90' => [
                'title' => 'New Testament in 90 Days',
                'description' => 'Matthew to Revelation in three months.',
                'entries' => self::spread(array_slice(array_keys(self::BOOKS), 39), 90),
            ],
            'gospels-30' => [
                'title' => 'The Gospels in 30 Days',
                'description' => 'Walk with Jesus through Matthew, Mark, Luke and John.',
                'entries' => self::spread(['Matthew', 'Mark', 'Luke', 'John'], 30),
            ],
            'psalms-proverbs-31' => [
                'title' => 'Proverbs in a Month',
                'description' => 'One chapter of Proverbs every day for a month of wisdom.',
                'entries' => collect(range(1, 31))->map(fn ($d) => ['day' => $d, 'title' => null, 'passages' => "Proverbs $d", 'reflection' => null])->all(),
            ],
        ];
    }

    /**
     * Spread every chapter of the given books evenly across N days.
     *
     * @param  array<int, string>  $books
     * @return array<int, array{day: int, title: ?string, passages: string, reflection: ?string}>
     */
    public static function spread(array $books, int $days): array
    {
        $chapters = [];
        foreach ($books as $book) {
            for ($c = 1; $c <= self::BOOKS[$book]; $c++) {
                $chapters[] = [$book, $c];
            }
        }

        $entries = [];
        $total = count($chapters);
        for ($d = 0; $d < $days; $d++) {
            $from = (int) floor($d * $total / $days);
            $to = (int) floor(($d + 1) * $total / $days) - 1;
            if ($to < $from) {
                $to = $from;
            }
            $entries[] = ['day' => $d + 1, 'title' => null, 'passages' => self::label(array_slice($chapters, $from, $to - $from + 1)), 'reflection' => null];
        }

        return $entries;
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $chapters
     */
    protected static function label(array $chapters): string
    {
        return collect($chapters)->groupBy(0)->map(function ($group, $book) {
            $first = $group->first()[1];
            $last = $group->last()[1];

            return $first === $last ? "{$book} {$first}" : "{$book} {$first}–{$last}";
        })->implode('; ');
    }
}
