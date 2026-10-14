<?php

namespace App\Support;

use App\Models\User;
use App\Services\Bible\BibleApiComProvider;
use App\Services\Bible\BibleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * One well-loved verse for each day, the same for everyone on that day. The references are listed here; the words come
 * from the member's own Bible translation (the same text the Bible screen shows) and are kept for the day.
 */
final class VerseOfTheDay
{
    /** Set when the Bible service could not be reached, so we stop asking it. */
    protected static bool $unavailable = false;

    /** @var list<string> */
    public const REFERENCES = [
        'John 3:16', 'Psalm 23:1-3', 'Philippians 4:13', 'Proverbs 3:5-6', 'Jeremiah 29:11', 'Isaiah 41:10', 'Romans 8:28', 'Joshua 1:9',
        'Matthew 11:28-30', 'Psalm 46:1-2', 'Isaiah 40:31', '2 Timothy 1:7', 'Romans 12:2', 'Psalm 119:105', 'Matthew 6:33', 'Hebrews 11:1',
        'Psalm 27:1', 'Galatians 5:22-23', 'Philippians 4:6-7', 'Psalm 121:1-2', 'Lamentations 3:22-23', 'Romans 15:13', '1 Peter 5:7', 'John 14:27',
        'Proverbs 16:3', 'Psalm 37:4-5', 'Psalm 34:8', 'Micah 6:8', 'Matthew 5:16', 'Ephesians 2:8-9', 'Colossians 3:23', 'James 1:5',
        '1 John 4:19', 'Deuteronomy 31:6', 'Isaiah 26:3', 'Psalm 91:1-2', 'Psalm 118:24', 'Nahum 1:7', 'Zephaniah 3:17', 'Romans 5:8',
        '2 Corinthians 5:7', 'Psalm 139:14', 'Proverbs 18:10', 'Psalm 30:5', 'John 15:5', 'Mark 10:27', 'Luke 1:37', 'Revelation 21:4',
        'Psalm 62:1-2', 'Isaiah 43:2', 'Romans 8:38-39', 'Galatians 6:9', 'Philippians 1:6', 'Psalm 103:2-4', 'Matthew 28:19-20', 'John 8:12',
    ];

    public static function reference(?Carbon $day = null): string
    {
        $day ??= now();

        return self::REFERENCES[(($day->year * 366) + $day->dayOfYear) % count(self::REFERENCES)];
    }

    /**
     * Today's verse for this member, or null when the Bible cannot be reached right now (the home screen then simply
     * leaves the card out).
     *
     * @return array{reference: string, text: string, translation: string, book: string, chapter: int, verse: int}|null
     */
    public static function forUser(User $user, BibleService $bible): ?array
    {
        $translation = $bible->defaultTranslation($user);
        $short = $bible->translations()[$translation]['short'] ?? strtoupper($translation);
        $today = now()->toDateString();

        // A few tries, in case one reference is not available in this translation. Only a few seconds are spent on the Bible
        // service: when it is slow or down the card is left out and Home still opens quickly.
        BibleApiComProvider::$timeout = 4;
        self::$unavailable = false;
        try {
            for ($i = 0; $i < 3; $i++) {
                $reference = self::reference(now()->addDays($i));
                $verse = Cache::remember("verse-of-the-day.{$today}.{$i}.{$translation}", now()->endOfDay(), fn () => self::resolve($bible, $reference, $translation, $short));
                if ($verse) {
                    return $verse;
                }
                if (self::$unavailable) {
                    break;
                }
            }
        } finally {
            BibleApiComProvider::$timeout = null;
        }

        return null;
    }

    /** @return array{reference: string, text: string, translation: string, book: string, chapter: int, verse: int}|null */
    protected static function resolve(BibleService $bible, string $reference, string $translation, string $short): ?array
    {
        $passage = $bible->passage($reference, $translation);
        $section = $passage['sections'][0] ?? null;
        $verses = $section['verses'] ?? [];
        self::$unavailable = (bool) ($section['error'] ?? false);
        if (! $section || $verses === [] || ($section['error'] ?? null)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', collect($verses)->pluck('t')->implode(' ')) ?? '');

        return $text === '' ? null : [
            'reference' => $passage['title'],
            'text' => $text,
            'translation' => $short,
            'book' => $section['book'],
            'chapter' => (int) $section['chapter'],
            'verse' => (int) $verses[0]['v'],
        ];
    }
}
