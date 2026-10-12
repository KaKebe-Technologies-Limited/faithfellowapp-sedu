<?php

namespace App\Support\Bible;

use Illuminate\Support\Str;

/**
 * The 66 books of the Protestant canon: USFM code, name, chapter count,
 * testament and the abbreviations people actually type ("Mt", "1 Cor", "Ps").
 */
final class Books
{
    /** code => [name, chapters, testament, aliases] */
    public const ALL = [
        'GEN' => ['Genesis', 50, 'OT', ['gen', 'ge', 'gn']],
        'EXO' => ['Exodus', 40, 'OT', ['exo', 'ex', 'exod']],
        'LEV' => ['Leviticus', 27, 'OT', ['lev', 'le', 'lv']],
        'NUM' => ['Numbers', 36, 'OT', ['num', 'nu', 'nm', 'nb']],
        'DEU' => ['Deuteronomy', 34, 'OT', ['deut', 'deu', 'de', 'dt']],
        'JOS' => ['Joshua', 24, 'OT', ['josh', 'jos', 'jsh']],
        'JDG' => ['Judges', 21, 'OT', ['judg', 'jdg', 'jg', 'jdgs']],
        'RUT' => ['Ruth', 4, 'OT', ['rut', 'ru', 'rth']],
        '1SA' => ['1 Samuel', 31, 'OT', ['1 sam', '1sam', '1 sa', '1sa', '1 samuel', 'i samuel', '1s']],
        '2SA' => ['2 Samuel', 24, 'OT', ['2 sam', '2sam', '2 sa', '2sa', '2 samuel', 'ii samuel', '2s']],
        '1KI' => ['1 Kings', 22, 'OT', ['1 kgs', '1kgs', '1 ki', '1ki', '1 kings', 'i kings', '1k']],
        '2KI' => ['2 Kings', 25, 'OT', ['2 kgs', '2kgs', '2 ki', '2ki', '2 kings', 'ii kings', '2k']],
        '1CH' => ['1 Chronicles', 29, 'OT', ['1 chron', '1chron', '1 chr', '1chr', '1 ch', '1ch', 'i chronicles']],
        '2CH' => ['2 Chronicles', 36, 'OT', ['2 chron', '2chron', '2 chr', '2chr', '2 ch', '2ch', 'ii chronicles']],
        'EZR' => ['Ezra', 10, 'OT', ['ezr', 'ez']],
        'NEH' => ['Nehemiah', 13, 'OT', ['neh', 'ne']],
        'EST' => ['Esther', 10, 'OT', ['esth', 'est', 'es']],
        'JOB' => ['Job', 42, 'OT', ['jb']],
        'PSA' => ['Psalms', 150, 'OT', ['psalm', 'ps', 'psa', 'pss', 'psm']],
        'PRO' => ['Proverbs', 31, 'OT', ['prov', 'pro', 'pr', 'prv']],
        'ECC' => ['Ecclesiastes', 12, 'OT', ['eccl', 'ecc', 'ec', 'qoh']],
        'SNG' => ['Song of Songs', 8, 'OT', ['song', 'song of solomon', 'sos', 'so', 'canticles', 'sng']],
        'ISA' => ['Isaiah', 66, 'OT', ['isa', 'is']],
        'JER' => ['Jeremiah', 52, 'OT', ['jer', 'je', 'jr']],
        'LAM' => ['Lamentations', 5, 'OT', ['lam', 'la']],
        'EZK' => ['Ezekiel', 48, 'OT', ['ezek', 'eze', 'ezk']],
        'DAN' => ['Daniel', 12, 'OT', ['dan', 'da', 'dn']],
        'HOS' => ['Hosea', 14, 'OT', ['hos', 'ho']],
        'JOL' => ['Joel', 3, 'OT', ['joe', 'jl']],
        'AMO' => ['Amos', 9, 'OT', ['amo', 'am']],
        'OBA' => ['Obadiah', 1, 'OT', ['obad', 'ob']],
        'JON' => ['Jonah', 4, 'OT', ['jnh', 'jon']],
        'MIC' => ['Micah', 7, 'OT', ['mic', 'mc']],
        'NAM' => ['Nahum', 3, 'OT', ['nah', 'na']],
        'HAB' => ['Habakkuk', 3, 'OT', ['hab', 'hb']],
        'ZEP' => ['Zephaniah', 3, 'OT', ['zeph', 'zep', 'zp']],
        'HAG' => ['Haggai', 2, 'OT', ['hag', 'hg']],
        'ZEC' => ['Zechariah', 14, 'OT', ['zech', 'zec', 'zc']],
        'MAL' => ['Malachi', 4, 'OT', ['mal', 'ml']],
        'MAT' => ['Matthew', 28, 'NT', ['matt', 'mat', 'mt']],
        'MRK' => ['Mark', 16, 'NT', ['mrk', 'mar', 'mk', 'mr']],
        'LUK' => ['Luke', 24, 'NT', ['luk', 'lk']],
        'JHN' => ['John', 21, 'NT', ['joh', 'jhn', 'jn']],
        'ACT' => ['Acts', 28, 'NT', ['act', 'ac', 'acts of the apostles']],
        'ROM' => ['Romans', 16, 'NT', ['rom', 'ro', 'rm']],
        '1CO' => ['1 Corinthians', 16, 'NT', ['1 cor', '1cor', '1 co', '1co', 'i corinthians']],
        '2CO' => ['2 Corinthians', 13, 'NT', ['2 cor', '2cor', '2 co', '2co', 'ii corinthians']],
        'GAL' => ['Galatians', 6, 'NT', ['gal', 'ga']],
        'EPH' => ['Ephesians', 6, 'NT', ['eph', 'ephes']],
        'PHP' => ['Philippians', 4, 'NT', ['phil', 'php', 'pp']],
        'COL' => ['Colossians', 4, 'NT', ['col', 'co']],
        '1TH' => ['1 Thessalonians', 5, 'NT', ['1 thess', '1thess', '1 th', '1th', 'i thessalonians']],
        '2TH' => ['2 Thessalonians', 3, 'NT', ['2 thess', '2thess', '2 th', '2th', 'ii thessalonians']],
        '1TI' => ['1 Timothy', 6, 'NT', ['1 tim', '1tim', '1 ti', '1ti', 'i timothy']],
        '2TI' => ['2 Timothy', 4, 'NT', ['2 tim', '2tim', '2 ti', '2ti', 'ii timothy']],
        'TIT' => ['Titus', 3, 'NT', ['tit', 'ti']],
        'PHM' => ['Philemon', 1, 'NT', ['philem', 'phm', 'pm']],
        'HEB' => ['Hebrews', 13, 'NT', ['heb']],
        'JAS' => ['James', 5, 'NT', ['jas', 'jm']],
        '1PE' => ['1 Peter', 5, 'NT', ['1 pet', '1pet', '1 pe', '1pe', '1 pt', 'i peter']],
        '2PE' => ['2 Peter', 3, 'NT', ['2 pet', '2pet', '2 pe', '2pe', '2 pt', 'ii peter']],
        '1JN' => ['1 John', 5, 'NT', ['1 jn', '1jn', '1 jo', '1john', 'i john']],
        '2JN' => ['2 John', 1, 'NT', ['2 jn', '2jn', '2 jo', '2john', 'ii john']],
        '3JN' => ['3 John', 1, 'NT', ['3 jn', '3jn', '3 jo', '3john', 'iii john']],
        'JUD' => ['Jude', 1, 'NT', ['jud', 'jd']],
        'REV' => ['Revelation', 22, 'NT', ['rev', 're', 'rv', 'revelations', 'the revelation']],
    ];

    /** @var array<string, string>|null alias => code */
    private static ?array $index = null;

    public static function name(string $code): string
    {
        return self::ALL[$code][0] ?? $code;
    }

    public static function chapters(string $code): int
    {
        return self::ALL[$code][1] ?? 0;
    }

    public static function exists(string $code): bool
    {
        return isset(self::ALL[$code]);
    }

    /**
     * Resolve any spelling ("Matt", "1 Cor", "Psalm", "song of solomon") to a USFM code.
     */
    public static function find(string $name): ?string
    {
        $key = self::normalise($name);
        if ($key === '') {
            return null;
        }

        if (self::$index === null) {
            self::$index = [];
            foreach (self::ALL as $code => [$bookName, , , $aliases]) {
                foreach ([$bookName, $code, ...$aliases] as $alias) {
                    self::$index[self::normalise($alias)] = $code;
                }
            }
        }

        return self::$index[$key] ?? self::$index[rtrim($key, '.')] ?? null;
    }

    public static function next(string $code): ?string
    {
        $codes = array_keys(self::ALL);
        $i = array_search($code, $codes, true);

        return $i === false ? null : ($codes[$i + 1] ?? null);
    }

    public static function previous(string $code): ?string
    {
        $codes = array_keys(self::ALL);
        $i = array_search($code, $codes, true);

        return $i === false || $i === 0 ? null : $codes[$i - 1];
    }

    /** Lowercase, roman numerals → digits, single spaces, no dots. */
    private static function normalise(string $name): string
    {
        $s = Str::lower(trim(str_replace(['.', '’', "'"], '', $name)));
        $s = preg_replace_callback('/^(iii|ii|i)\s+(?=[a-z])/', fn ($m) => ['i' => '1', 'ii' => '2', 'iii' => '3'][$m[1]].' ', $s);
        $s = preg_replace('/^([123])\s*([a-z])/', '$1 $2', $s);

        return preg_replace('/\s+/', ' ', $s);
    }
}
