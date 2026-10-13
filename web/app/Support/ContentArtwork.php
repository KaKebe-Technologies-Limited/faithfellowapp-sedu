<?php

namespace App\Support;

use GdImage;
use Illuminate\Database\Eloquent\Model;

/**
 * Cover pictures for events, sermons, devotionals and campaigns that have no photo of their own, so the app is
 * never a wall of text. The scene follows what the item is about (a youth camp gets a worship stage, a prayer
 * night gets candles, a Bible study gets an open book of light, Christmas gets a star over the hills…) and is
 * tinted with the church's brand colour. No text is drawn on the picture: the app puts the title on top.
 *
 * Built on the same GD drawing kit as the church banners (ChurchArtwork); the result is stable for a given
 * title and colour, and the churches replace it simply by uploading a real photo.
 */
class ContentArtwork extends ChurchArtwork
{
    /** Words in a title that point to a scene (first match wins, so list the most specific first). */
    protected const KEYWORDS = [
        'nativity' => ['christmas', 'carol', 'nativity', 'advent', 'bethlehem', 'epiphany'],
        'cross' => ['easter', 'good friday', 'cross', 'calvary', 'crucifix', 'resurrection', 'passion', 'revival', 'crusade', 'repent'],
        'bokeh' => ['prayer', 'vigil', 'intercess', 'fasting', 'all night', 'candle', 'watchnight', 'wedding', 'marriage', 'couples', 'anniversary', 'memorial', 'funeral', 'remembrance'],
        'book' => ['bible', 'study', 'scripture', 'devotion', 'teaching', 'class', 'training', 'discipleship', 'reading', 'lesson', 'word of', 'school', 'seminar', 'workshop', 'verse', 'psalm', 'gospel'],
        'worship' => ['youth', 'teen', 'camp', 'conference', 'concert', 'praise', 'worship', 'band', 'choir', 'festival', 'gala', 'rally', 'convention', 'night of', 'talent'],
        'waves' => ['baptism', 'outreach', 'mission', 'community', 'charity', 'clean-up', 'fun walk', 'picnic', 'sport', 'fun day', 'family', 'children', 'kids', 'women', 'men\'s', 'harvest', 'drive', 'relief', 'feeding', 'welfare', 'school fees'],
    ];

    /** What to use when the title says nothing, so a feed of plain titles still has variety. */
    protected const FALLBACKS = [
        'event' => ['worship', 'sunrise', 'waves', 'bokeh'],
        'sermon' => ['sunrise', 'cross', 'book', 'worship'],
        'devotional' => ['sunrise', 'book', 'waves', 'bokeh'],
        'campaign' => ['sunrise', 'waves', 'cross', 'worship'],
    ];

    /** A campaign's type decides its scene when the title alone does not. */
    public const CAMPAIGN_THEMES = [
        'building' => 'sunrise', 'missions' => 'waves', 'welfare' => 'bokeh', 'event' => 'worship', 'equipment' => 'worship',
        'pastor_support' => 'bokeh', 'emergency' => 'bokeh', 'youth' => 'worship', 'education' => 'book',
    ];

    /** Which scene a piece of content gets: words in the title first, then the hint (a campaign's type), then a varied default. */
    public static function themeFor(string $kind, string $title, int $seed = 0, ?string $hint = null): string
    {
        $lower = mb_strtolower($title);
        foreach (self::KEYWORDS as $theme => $words) {
            foreach ($words as $word) {
                if (str_contains($lower, $word)) {
                    return $theme;
                }
            }
        }
        if ($hint && in_array($hint, ['sunrise', 'worship', 'cross', 'bokeh', 'waves', 'book', 'nativity'], true)) {
            return $hint;
        }
        $options = self::FALLBACKS[$kind] ?? self::FALLBACKS['event'];

        return $options[abs($seed) % count($options)];
    }

    /** Bump when the drawing changes so every cached picture is redrawn. */
    public const VERSION = 1;

    /** Public URL of an item's generated cover. The version in the query string changes with the title and colour, so caches stay right. */
    public static function url(string $kind, Model $model): string
    {
        return route('art', ['kind' => $kind, 'id' => $model->getKey(), 'v' => self::stamp($kind, $model)]);
    }

    public static function stamp(string $kind, Model $model): string
    {
        return substr(md5(self::VERSION.'|'.$kind.'|'.$model->title.'|'.self::brand($model).'|'.self::hint($kind, $model)), 0, 8);
    }

    /** Draw (once) and return the file for an item. */
    public static function file(string $kind, Model $model): string
    {
        $path = storage_path('app/artwork/content/'.$kind.'-'.$model->getKey().'-'.self::stamp($kind, $model).'.jpg');
        if (! is_file($path)) {
            self::cover($path, $kind, (string) $model->title, self::brand($model), (int) $model->getKey(), self::hint($kind, $model) ?: null);
        }

        return $path;
    }

    protected static function brand(Model $model): string
    {
        return $model->church?->brand_color ?: '#BC2649';
    }

    protected static function hint(string $kind, Model $model): string
    {
        return $kind === 'campaign' ? (self::CAMPAIGN_THEMES[$model->type] ?? '') : '';
    }

    /** A 16:9 cover picture (JPEG). */
    public static function cover(string $path, string $kind, string $title, string $hex = '#BC2649', int $seed = 1, ?string $hint = null, int $w = 1200, int $h = 675): string
    {
        $theme = self::themeFor($kind, $title, $seed, $hint);
        mt_srand(crc32($theme.$hex.$seed.$title));
        $scale = 1.4;
        $W = (int) ($w * $scale);
        $H = (int) ($h * $scale);
        $im = self::canvas($W, $H);
        $brand = self::rgb($hex);

        match ($theme) {
            'worship' => self::worship($im, $W, $H, $brand),
            'cross' => self::crossNight($im, $W, $H, $brand),
            'bokeh' => self::bokehScene($im, $W, $H, $brand),
            'waves' => self::waves($im, $W, $H, $brand),
            'book' => self::bookScene($im, $W, $H, $brand),
            'nativity' => self::nativityScene($im, $W, $H, $brand),
            default => self::sunrise($im, $W, $H, $brand),
        };
        self::bottomShade($im, $W, $H, 0.5);

        return self::save($im, $path, $w, $h, 'jpg');
    }

    /** A tall poster (JPEG) of one scene, for the app's welcome screen. */
    public static function poster(string $path, string $theme, string $hex = '#BC2649', int $seed = 1, int $w = 900, int $h = 1600): string
    {
        return self::scene($path, $theme, $hex, $seed, $w, $h);
    }

    /** Warm light pouring out of an open book. */
    protected static function bookScene(GdImage $im, int $W, int $H, array $brand): void
    {
        $deep = self::mix($brand, [24, 8, 26], 0.7);
        self::vgradient($im, [[0, $deep], [0.45, self::mix($brand, [90, 30, 60], 0.35)], [0.78, self::mix($brand, [236, 138, 84], 0.55)], [1, [255, 214, 150]]]);

        $cx = (int) ($W * (0.5 + mt_rand(-8, 8) / 100));
        $cy = (int) ($H * 0.66);
        self::glow($im, $cx, $cy - (int) ($H * 0.1), (int) ($W * 0.5), [255, 214, 150], 0.7);
        self::glow($im, $cx, $cy - (int) ($H * 0.04), (int) ($H * 0.3), [255, 246, 220], 0.9);
        self::rays($im, $cx, $cy - (int) ($H * 0.05), 20, [255, 236, 190], 0.08, $W);
        self::bokeh($im, $W, (int) ($H * 0.7), 46, [[255, 232, 180], [255, 255, 255], self::mix($brand, [255, 200, 200], 0.5)], 0.05, 0.4);

        // The book: a dark cover edge, then two curved cream pages with lines of text.
        $span = $W * 0.27;
        $dip = $H * 0.035;
        $cover = self::col($im, self::mix($brand, [30, 10, 20], 0.7));
        $page = self::col($im, [255, 247, 232]);
        $shade = self::col($im, [236, 218, 190]);
        $ink = self::col($im, [190, 160, 130], 20);

        $pagePoly = function (int $side, float $grow) use ($cx, $cy, $span, $dip, $H) {
            $pts = [];
            for ($i = 0; $i <= 24; $i++) { // top edge: spine → outer corner, bowed upward
                $t = $i / 24;
                $pts[] = (int) ($cx + $side * $span * $t * (1 + $grow));
                $pts[] = (int) ($cy - $H * 0.1 - $dip * sin($t * M_PI * 0.9) + $H * 0.045 * $t * $t + $H * $grow * 0.4);
            }
            for ($i = 24; $i >= 0; $i--) { // bottom edge: outer corner → spine
                $t = $i / 24;
                $pts[] = (int) ($cx + $side * $span * $t * (1 + $grow));
                $pts[] = (int) ($cy + $H * 0.1 + $dip * 0.4 * sin($t * M_PI * 0.9) + $H * 0.05 * $t * $t + $H * $grow * 0.4);
            }

            return $pts;
        };
        foreach ([-1, 1] as $side) {
            imagefilledpolygon($im, $pagePoly($side, 0.035), $cover);
        }
        foreach ([-1, 1] as $side) {
            imagefilledpolygon($im, $pagePoly($side, 0.0), $page);
            // Soft shade toward the spine, as pages curve down.
            for ($i = 0; $i < 7; $i++) {
                $t = $i / 7;
                $strip = [];
                $x1 = (int) ($cx + $side * $span * $t * 0.16);
                $x2 = (int) ($cx + $side * $span * ($t + 0.14) * 0.16);
                imagefilledpolygon($im, [$x1, (int) ($cy - $H * 0.11), $x2, (int) ($cy - $H * 0.11), $x2, (int) ($cy + $H * 0.115), $x1, (int) ($cy + $H * 0.115)], self::col($im, [210, 180, 150], 118 - $i * 2));
            }
            // Lines of text.
            imagesetthickness($im, max(2, (int) ($H * 0.004)));
            for ($line = 0; $line < 7; $line++) {
                $y0 = $cy - $H * 0.07 + $line * $H * 0.024;
                $end = $line % 3 === 2 ? 0.55 : 0.82;
                $prev = null;
                for ($i = 2; $i <= 20; $i++) {
                    $t = $i / 24 * $end + 0.1;
                    $x = (int) ($cx + $side * $span * $t);
                    $y = (int) ($y0 - $dip * 0.7 * sin($t * M_PI * 0.9) + $H * 0.04 * $t * $t);
                    if ($prev) {
                        imageline($im, $prev[0], $prev[1], $x, $y, $ink);
                    }
                    $prev = [$x, $y];
                }
            }
            imagesetthickness($im, 1);
        }
        // The spine crease.
        imagesetthickness($im, max(2, (int) ($H * 0.005)));
        imageline($im, $cx, (int) ($cy - $H * 0.105), $cx, (int) ($cy + $H * 0.1), $shade);
        imagesetthickness($im, 1);
        self::hill($im, $W, $H, $H * 0.93, $H * 0.02, 0.003, 2.1, [14, 4, 10]);
    }

    /** A bright star over quiet hills. */
    protected static function nativityScene(GdImage $im, int $W, int $H, array $brand): void
    {
        self::vgradient($im, [[0, [6, 10, 34]], [0.55, self::mix($brand, [22, 20, 70], 0.7)], [0.88, self::mix($brand, [210, 120, 110], 0.4)], [1, [250, 190, 130]]]);
        self::stars($im, $W, (int) ($H * 0.7), 260);

        $sx = (int) ($W * (0.58 + mt_rand(-12, 12) / 100));
        $sy = (int) ($H * 0.24);
        self::glow($im, $sx, $sy, (int) ($W * 0.3), [200, 220, 255], 0.55);
        self::glow($im, $sx, $sy, (int) ($H * 0.12), [255, 255, 240], 0.95);
        // A long beam falling to the stable.
        self::beam($im, $sx, $sy, $sx - (int) ($W * 0.06), (int) ($H * 0.74), (int) ($W * 0.08), [255, 240, 200], 0.1);
        // The star itself: two crossed diamonds.
        $white = self::col($im, [255, 255, 245]);
        $r = (int) ($H * 0.055);
        imagefilledpolygon($im, [$sx, $sy - $r * 2, $sx + (int) ($r * 0.28), $sy, $sx, $sy + $r * 2, $sx - (int) ($r * 0.28), $sy], $white);
        imagefilledpolygon($im, [$sx - $r * 2, $sy, $sx, $sy - (int) ($r * 0.28), $sx + $r * 2, $sy, $sx, $sy + (int) ($r * 0.28)], $white);

        $hillTop = (int) ($H * 0.74);
        self::hill($im, $W, $H, $H * 0.76, $H * 0.035, 0.0021, 0.4, self::mix($brand, [34, 18, 60], 0.75));
        self::hill($im, $W, $H, $H * 0.84, $H * 0.03, 0.0032, 1.9, [18, 8, 28]);
        // A small stable on the near hill, with a warm lit doorway.
        $bx = (int) ($sx - $W * 0.06);
        $by = (int) ($H * 0.835);
        $bw = (int) ($W * 0.05);
        $dark = self::col($im, [10, 4, 14]);
        imagefilledrectangle($im, $bx - $bw, $by - (int) ($bw * 0.8), $bx + $bw, $by + 10, $dark);
        imagefilledpolygon($im, [$bx - (int) ($bw * 1.25), $by - (int) ($bw * 0.78), $bx, $by - (int) ($bw * 1.7), $bx + (int) ($bw * 1.25), $by - (int) ($bw * 0.78)], $dark);
        self::glow($im, $bx, $by - (int) ($bw * 0.3), (int) ($bw * 1.1), [255, 200, 110], 0.9);
        imagefilledrectangle($im, $bx - (int) ($bw * 0.28), $by - (int) ($bw * 0.62), $bx + (int) ($bw * 0.28), $by + 4, self::col($im, [255, 214, 140]));
        self::hill($im, $W, $H, $H * 0.95, $H * 0.02, 0.004, 3.1, [6, 2, 8]);
        unset($hillTop);
    }
}
