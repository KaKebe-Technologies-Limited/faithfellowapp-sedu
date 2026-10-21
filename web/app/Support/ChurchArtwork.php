<?php

namespace App\Support;

use GdImage;

/**
 * Generates clean, professional-looking placeholder artwork for churches:
 * square emblem logos, wide banners and home-slider images (sunrise hills,
 * worship stage, cross at night, bokeh lights, flowing waves).
 *
 * Images are drawn with GD at a higher resolution and downsampled, so edges
 * are smooth. Used by the demo seeder and `php artisan ff:church-artwork`.
 */
class ChurchArtwork
{
    public const THEMES = ['sunrise', 'worship', 'cross', 'bokeh', 'waves'];

    protected static ?string $font = null;

    /* ── Public API ───────────────────────────────────────────────────── */

    /**
     * Square logo (512×512 PNG): brand gradient, white emblem, initials.
     */
    public static function logo(string $path, string $name, string $hex, int $variant = 0): string
    {
        $S = 1024;
        $im = self::canvas($S, $S);
        $brand = self::rgb($hex);
        $top = self::mix($brand, [255, 255, 255], 0.12);
        $bottom = self::mix($brand, [20, 6, 12], 0.42);
        self::vgradient($im, [[0, $top], [1, $bottom]]);
        self::glow($im, 200, 160, 620, [255, 255, 255], 0.22);

        $white = self::col($im, [255, 255, 255]);
        $cx = 512;
        $cy = 410;

        match ($variant % 5) {
            0 => self::emblemRingCross($im, $cx, $cy, $white, $bottom),
            1 => self::emblemSunburst($im, $cx, $cy, $white),
            2 => self::emblemBook($im, $cx, $cy, $white),
            3 => self::emblemFlame($im, $cx, $cy, $white),
            default => self::emblemChapel($im, $cx, $cy, $white, $bottom),
        };

        self::centeredText($im, self::initials($name), 118, $S / 2, 840, [255, 255, 255], 6);
        self::centeredText($im, mb_strtoupper(self::shortName($name)), 34, $S / 2, 920, [255, 255, 255], 2, 40);

        return self::save($im, $path, 512, 512, 'png');
    }

    /**
     * Wide banner (1600×600 JPG) for the church page header.
     */
    public static function banner(string $path, string $hex, int $seed = 1, ?string $theme = null): string
    {
        return self::placeholder($path, $hex, $seed, 1600, 600);
    }

    /**
     * Home-slider image (1600×700 JPG). No text: captions are added in HTML so
     * churches can edit and translate them.
     */
    public static function slide(string $path, string $theme, string $hex, int $seed = 1): string
    {
        return self::scene($path, $theme, $hex, $seed, 1600, 700);
    }

    /* ── The FaithFellows placeholder ─────────────────────────────────────── */

    /**
     * The picture for anything that has no photo of its own (a radio track, a podcast, an event, a church banner…): a soft gradient in the
     * brand colour, the FaithFellows mark big and faint in the background, light pooling in the corners, and the white mark in the middle on a
     * soft glow. It looks like part of the app and not like an empty box. No text is drawn (the app puts the title on top), and the middle
     * stays clear, so it still works when the app crops it to a square. The `$seed` picks one of four looks, so a list is not all the same.
     *
     * `$layout` says where the mark sits, because the app lays things over the picture: 'center' (radio and podcast covers, shown as squares),
     * 'top' (the default: a title or a date sits over the lower half) and 'compact' (sermons, which have a play button in the middle).
     */
    public static function placeholder(string $path, string $hex = '#BC2649', int $seed = 1, int $w = 1200, int $h = 675, string $format = 'jpg', string $layout = 'top'): string
    {
        $scale = 1.4;
        $W = (int) ($w * $scale);
        $H = (int) ($h * $scale);
        $im = self::canvas($W, $H);
        $brand = self::rgb($hex);
        $v = abs($seed) % 4;

        $deep = self::mix($brand, [24, 6, 16], 0.6);
        $light = self::mix($brand, [255, 255, 255], 0.3);
        self::vgradient($im, [
            [[0, $deep], [0.55, $brand], [1, $light]],
            [[0, $light], [0.5, $brand], [1, $deep]],
            [[0, $brand], [0.6, self::mix($brand, [255, 255, 255], 0.12)], [1, $deep]],
            [[0, $deep], [0.45, $brand], [1, self::mix($brand, [255, 200, 215], 0.35)]],
        ][$v]);

        // Light pooling in two corners, so the colour has depth.
        self::glow($im, (int) ($W * [0.18, 0.82, 0.3, 0.74][$v]), (int) ($H * [0.2, 0.78, 0.8, 0.24][$v]), (int) ($W * 0.5), self::mix($brand, [255, 255, 255], 0.55), 0.24);
        self::glow($im, (int) ($W * [0.86, 0.12, 0.8, 0.16][$v]), (int) ($H * [0.84, 0.18, 0.2, 0.86][$v]), (int) ($W * 0.38), self::mix($brand, [255, 190, 210], 0.5), 0.22);

        // The mark, big and faint, off to one side.
        self::layMark($im, 'mark-faint', (int) ($W * [0.84, 0.14, 0.9, 0.1][$v]), (int) ($H * [0.46, 0.56, 0.7, 0.34][$v]), (int) ($H * 1.5));

        // The mark in the middle, on a soft glow and a soft shadow.
        [$at, $big] = ['center' => [0.5, 0.34], 'compact' => [0.26, 0.2]][$layout] ?? [0.33, 0.26];
        $cx = (int) ($W / 2);
        $cy = (int) ($H * $at);
        self::glow($im, $cx, $cy, (int) ($H * $big * 1.4), self::mix($brand, [255, 255, 255], 0.7), 0.3);
        self::layMark($im, 'mark-shadow', $cx, $cy + (int) ($H * 0.02), (int) ($H * $big * 1.4));
        self::layMark($im, 'mark', $cx, $cy, (int) ($H * $big));

        self::bottomShade($im, $W, $H, 0.4);

        return self::save($im, $path, $w, $h, $format);
    }

    /** Lays one of the brand marks (resources/brand) on the picture, centred on a point and `$size` pixels wide. */
    protected static function layMark(GdImage $im, string $mark, int $cx, int $cy, int $size): void
    {
        $file = resource_path("brand/{$mark}.png");
        if (! is_file($file) || ! ($png = @imagecreatefrompng($file))) {
            return;
        }
        imagealphablending($im, true);
        imagecopyresampled($im, $png, (int) ($cx - $size / 2), (int) ($cy - $size / 2), 0, 0, $size, $size, imagesx($png), imagesy($png));
        imagedestroy($png);
    }

    /* ── Scenes ───────────────────────────────────────────────────────── */

    protected static function scene(string $path, string $theme, string $hex, int $seed, int $w, int $h): string
    {
        mt_srand(crc32($theme.$hex.$seed));
        $scale = 1.5;
        $W = (int) ($w * $scale);
        $H = (int) ($h * $scale);
        $im = self::canvas($W, $H);
        $brand = self::rgb($hex);

        match ($theme) {
            'worship' => self::worship($im, $W, $H, $brand),
            'cross' => self::crossNight($im, $W, $H, $brand),
            'bokeh' => self::bokehScene($im, $W, $H, $brand),
            'waves' => self::waves($im, $W, $H, $brand),
            default => self::sunrise($im, $W, $H, $brand),
        };

        // Gentle vignette so captions always read well.
        self::bottomShade($im, $W, $H, 0.45);

        return self::save($im, $path, $w, $h, 'jpg');
    }

    protected static function sunrise(GdImage $im, int $W, int $H, array $brand): void
    {
        $deep = self::mix($brand, [25, 8, 30], 0.65);
        self::vgradient($im, [[0, $deep], [0.35, self::mix($brand, [90, 20, 60], 0.3)], [0.62, self::mix($brand, [245, 120, 70], 0.55)], [0.78, [252, 196, 110]], [1, [255, 226, 160]]]);
        $sunX = (int) ($W * (0.55 + mt_rand(-10, 10) / 100));
        $sunY = (int) ($H * 0.66);
        self::glow($im, $sunX, $sunY, (int) ($W * 0.55), [255, 214, 140], 0.75);
        self::glow($im, $sunX, $sunY, (int) ($H * 0.16), [255, 247, 220], 0.95);
        self::rays($im, $sunX, $sunY, 16, [255, 236, 190], 0.07, $W * 1.2);

        $layers = [
            [0.70, 0.045, self::mix($brand, [70, 20, 50], 0.55)],
            [0.78, 0.04, self::mix($brand, [40, 10, 30], 0.7)],
            [0.86, 0.035, self::mix($brand, [22, 6, 18], 0.82)],
            [0.94, 0.03, [14, 4, 10]],
        ];
        foreach ($layers as $i => [$base, $amp, $color]) {
            self::hill($im, $W, $H, $H * $base, $H * $amp, 0.0016 + $i * 0.0007, mt_rand(0, 628) / 100, $color);
        }
        self::birds($im, $W, $H, 5);
    }

    protected static function worship(GdImage $im, int $W, int $H, array $brand): void
    {
        $stage = self::mix($brand, [10, 2, 8], 0.72);
        self::vgradient($im, [[0, [8, 2, 8]], [0.45, $stage], [0.8, self::mix($brand, [30, 6, 20], 0.5)], [1, [12, 3, 9]]]);

        // Stage lights: coloured beams from the top.
        $beams = [[0.18, [255, 255, 255]], [0.38, self::mix($brand, [255, 120, 170], 0.4)], [0.62, [130, 170, 255]], [0.82, [255, 210, 150]]];
        foreach ($beams as [$x, $color]) {
            $ox = (int) ($W * $x);
            self::beam($im, $ox, -20, $ox + mt_rand(-260, 260), (int) ($H * 0.95), (int) ($W * 0.16), $color, 0.16);
            self::glow($im, $ox, 0, (int) ($H * 0.18), $color, 0.6);
        }
        self::glow($im, (int) ($W * 0.5), (int) ($H * 0.62), (int) ($W * 0.5), self::mix($brand, [255, 255, 255], 0.3), 0.35);
        self::bokeh($im, $W, $H, 70, [[255, 255, 255], [255, 200, 150], self::mix($brand, [255, 255, 255], 0.5)], 0.05, 0.55);
        self::crowd($im, $W, $H);
    }

    protected static function crossNight(GdImage $im, int $W, int $H, array $brand): void
    {
        self::vgradient($im, [[0, [6, 8, 26]], [0.5, self::mix($brand, [20, 14, 50], 0.75)], [0.85, self::mix($brand, [250, 150, 110], 0.55)], [1, [255, 200, 140]]]);
        self::stars($im, $W, (int) ($H * 0.6), 220);
        $cx = (int) ($W * (0.62 + mt_rand(-6, 6) / 100));
        $hillTop = (int) ($H * 0.62);
        self::glow($im, $cx, $hillTop, (int) ($W * 0.45), [255, 200, 150], 0.65);
        self::glow($im, $cx, (int) ($hillTop - $H * 0.16), (int) ($H * 0.28), [255, 240, 210], 0.5);
        self::rays($im, $cx, (int) ($hillTop - $H * 0.18), 18, [255, 230, 190], 0.06, $W);

        // A soft hill with the cross on its crest.
        $dark = [14, 6, 14];
        $points = [];
        for ($x = -40; $x <= $W + 40; $x += 24) {
            $d = ($x - $cx) / ($W * 0.42);
            $points[] = $x;
            $points[] = (int) ($hillTop + $H * 0.32 * min(1, $d * $d) + $H * 0.01 * sin($x * 0.004));
        }
        array_push($points, $W + 40, $H + 10, -40, $H + 10);
        imagefilledpolygon($im, $points, self::col($im, $dark));
        $bar = (int) max(6, $W * 0.011);
        $height = (int) ($H * 0.3);
        imagefilledrectangle($im, $cx - $bar, $hillTop - $height, $cx + $bar, $hillTop + 4, self::col($im, $dark));
        $arm = (int) ($height * 0.34);
        $armY = $hillTop - (int) ($height * 0.72);
        imagefilledrectangle($im, $cx - $arm, $armY - $bar, $cx + $arm, $armY + $bar, self::col($im, $dark));
        self::hill($im, $W, $H, $H * 0.92, $H * 0.025, 0.003, 1.3, [8, 3, 8]);
    }

    protected static function bokehScene(GdImage $im, int $W, int $H, array $brand): void
    {
        self::vgradient($im, [[0, self::mix($brand, [30, 8, 20], 0.55)], [0.55, self::mix($brand, [120, 40, 30], 0.35)], [1, self::mix($brand, [40, 12, 10], 0.6)]]);
        self::glow($im, (int) ($W * 0.25), (int) ($H * 0.35), (int) ($W * 0.45), [255, 190, 120], 0.45);
        self::glow($im, (int) ($W * 0.8), (int) ($H * 0.7), (int) ($W * 0.4), self::mix($brand, [255, 160, 200], 0.5), 0.4);
        self::bokeh($im, $W, $H, 120, [[255, 214, 150], [255, 170, 110], [255, 240, 220], self::mix($brand, [255, 200, 220], 0.4)], 0.08, 0.5);
        // Candle flames along the bottom.
        $n = mt_rand(5, 8);
        for ($i = 0; $i < $n; $i++) {
            $x = (int) ($W * (0.12 + $i * 0.76 / max(1, $n - 1)) + mt_rand(-30, 30));
            $base = (int) ($H * (0.82 + mt_rand(-4, 6) / 100));
            self::candle($im, $x, $base, (int) ($H * (0.12 + mt_rand(0, 10) / 100)));
        }
    }

    protected static function waves(GdImage $im, int $W, int $H, array $brand): void
    {
        $deep = self::mix($brand, [18, 6, 28], 0.6);
        self::vgradient($im, [[0, $deep], [1, self::mix($brand, [60, 20, 70], 0.45)]]);
        self::glow($im, (int) ($W * 0.15), (int) ($H * 0.2), (int) ($W * 0.5), self::mix($brand, [255, 180, 210], 0.5), 0.45);
        self::glow($im, (int) ($W * 0.9), (int) ($H * 0.85), (int) ($W * 0.45), [255, 190, 120], 0.35);
        $palette = [self::mix($brand, [255, 255, 255], 0.3), [255, 190, 130], self::mix($brand, [255, 140, 190], 0.5), self::mix($brand, [110, 60, 210], 0.45), [255, 255, 255]];
        $crest = self::col($im, [255, 255, 255], 85);
        imagesetthickness($im, 3);
        for ($i = 0; $i < 7; $i++) {
            $base = $H * (0.32 + $i * 0.09);
            $color = $palette[$i % count($palette)];
            $points = [];
            $phase = mt_rand(0, 628) / 100;
            for ($x = -20; $x <= $W + 20; $x += 16) {
                $points[] = $x;
                $points[] = (int) ($base + $H * 0.07 * sin($x * 0.0024 + $phase) + $H * 0.03 * sin($x * 0.0061 + $phase * 2));
            }
            $line = $points;
            array_push($points, $W + 20, $H + 10, -20, $H + 10);
            imagefilledpolygon($im, $points, self::col($im, $color, (int) (127 * (1 - 0.2))));
            // Thin highlight along each crest for a polished, glassy look.
            for ($p = 0; $p < count($line) - 2; $p += 2) {
                imageline($im, $line[$p], $line[$p + 1], $line[$p + 2], $line[$p + 3], $crest);
            }
        }
        imagesetthickness($im, 1);
        self::bokeh($im, $W, (int) ($H * 0.6), 40, [[255, 255, 255]], 0.04, 0.25);
    }

    /* ── Emblems (logo) ───────────────────────────────────────────────── */

    protected static function emblemRingCross(GdImage $im, int $cx, int $cy, int $white, array $bg): void
    {
        imagefilledellipse($im, $cx, $cy, 470, 470, $white);
        imagefilledellipse($im, $cx, $cy, 410, 410, self::col($im, self::mix($bg, [255, 255, 255], 0.08)));
        self::cross($im, $cx, $cy + 8, 300, 46, $white);
    }

    protected static function emblemSunburst(GdImage $im, int $cx, int $cy, int $white): void
    {
        $soft = self::col($im, [255, 255, 255], 90);
        for ($i = 0; $i < 16; $i++) {
            $a = $i * M_PI / 8;
            $a2 = $a + 0.09;
            imagefilledpolygon($im, [$cx, $cy, (int) ($cx + 300 * cos($a)), (int) ($cy + 300 * sin($a)), (int) ($cx + 300 * cos($a2)), (int) ($cy + 300 * sin($a2))], $soft);
        }
        self::cross($im, $cx, $cy, 330, 54, $white);
    }

    protected static function emblemBook(GdImage $im, int $cx, int $cy, int $white): void
    {
        $y = $cy + 40;
        imagefilledpolygon($im, [$cx - 12, $y - 40, $cx - 240, $y - 80, $cx - 240, $y + 120, $cx - 12, $y + 160], $white);
        imagefilledpolygon($im, [$cx + 12, $y - 40, $cx + 240, $y - 80, $cx + 240, $y + 120, $cx + 12, $y + 160], $white);
        $line = self::col($im, [255, 255, 255], 70);
        foreach ([[-210, -40], [-210, 0], [-210, 40], [30, -40], [30, 0], [30, 40]] as [$dx, $dy]) {
            imagefilledrectangle($im, $cx + $dx, $y + $dy, $cx + $dx + 160, $y + $dy + 8, $line);
        }
        self::cross($im, $cx, $cy - 150, 170, 30, $white);
    }

    protected static function emblemFlame(GdImage $im, int $cx, int $cy, int $white): void
    {
        // Classic teardrop: x = sin(t)·sin(t/2)^m, y = cos(t) — pointed top, round base.
        $flame = function (int $x, int $y, int $w, int $h, int $color) use ($im) {
            $pts = [];
            for ($t = 0; $t < 2 * M_PI; $t += 0.05) {
                $pts[] = (int) ($x + $w / 2 * sin($t) * pow(sin($t / 2), 1.6));
                $pts[] = (int) ($y - $h / 2 * cos($t));
            }
            imagefilledpolygon($im, $pts, $color);
        };
        $flame($cx, $cy, 330, 460, $white);
        $flame($cx, $cy + 80, 170, 280, self::col($im, [255, 205, 120]));
        $flame($cx, $cy + 120, 80, 150, self::col($im, [255, 245, 220]));
    }

    protected static function emblemChapel(GdImage $im, int $cx, int $cy, int $white, array $bg): void
    {
        $base = $cy + 170;
        imagefilledpolygon($im, [$cx - 230, $base, $cx - 230, $cy + 10, $cx, $cy - 120, $cx + 230, $cy + 10, $cx + 230, $base], $white);
        imagefilledpolygon($im, [$cx - 60, $cy - 82, $cx - 60, $cy - 180, $cx, $cy - 250, $cx + 60, $cy - 180, $cx + 60, $cy - 82], $white);
        self::cross($im, $cx, $cy - 290, 110, 20, $white);
        // Arched door and a small rose window.
        $cut = self::col($im, self::mix($bg, [0, 0, 0], 0.15));
        imagefilledellipse($im, $cx, $base - 70, 104, 104, $cut);
        imagefilledrectangle($im, $cx - 52, $base - 70, $cx + 52, $base, $cut);
        imagefilledellipse($im, $cx, $cy - 20, 54, 54, $cut);
    }

    /* ── Drawing helpers ──────────────────────────────────────────────── */

    protected static function cross(GdImage $im, int $cx, int $cy, int $size, int $thick, int $color): void
    {
        $half = (int) ($size / 2);
        imagefilledrectangle($im, $cx - (int) ($thick / 2), $cy - $half, $cx + (int) ($thick / 2), $cy + $half, $color);
        $armY = $cy - (int) ($size * 0.18);
        $arm = (int) ($size * 0.36);
        imagefilledrectangle($im, $cx - $arm, $armY - (int) ($thick / 2), $cx + $arm, $armY + (int) ($thick / 2), $color);
    }

    protected static function hill(GdImage $im, int $W, int $H, float $base, float $amp, float $freq, float $phase, array $color): void
    {
        $points = [];
        for ($x = -40; $x <= $W + 40; $x += 18) {
            $points[] = $x;
            $points[] = (int) ($base + $amp * sin($x * $freq + $phase) + $amp * 0.45 * sin($x * $freq * 2.7 + $phase * 1.7));
        }
        array_push($points, $W + 40, $H + 10, -40, $H + 10);
        imagefilledpolygon($im, $points, self::col($im, $color));
    }

    protected static function crowd(GdImage $im, int $W, int $H): void
    {
        $dark = self::col($im, [6, 2, 6]);
        $x = -30;
        while ($x < $W + 40) {
            $r = mt_rand(16, 26);
            $headY = (int) ($H - mt_rand(130, 190));
            imagefilledellipse($im, $x, $headY, $r * 2, (int) ($r * 2.3), $dark);
            imagefilledellipse($im, $x, $headY + (int) ($r * 3.2), (int) ($r * 4.2), (int) ($r * 4.4), $dark);
            imagefilledrectangle($im, $x - (int) ($r * 2.1), $headY + (int) ($r * 3.2), $x + (int) ($r * 2.1), $H, $dark);

            if (mt_rand(0, 100) < 42) {
                // Raised hands in worship.
                foreach (mt_rand(0, 1) ? [-1, 1] : [mt_rand(0, 1) ? -1 : 1] as $side) {
                    $sx = $x + $side * (int) ($r * 1.4);
                    $sy = $headY + (int) ($r * 2.4);
                    $hx = $sx + $side * mt_rand((int) ($r * 1.2), (int) ($r * 2.4));
                    $hy = $headY - mt_rand((int) ($r * 2.2), (int) ($r * 3.6));
                    $t = (int) max(4, $r * 0.28);
                    imagefilledpolygon($im, [$sx - $t, $sy, $sx + $t, $sy, $hx + (int) ($t * 0.7), $hy, $hx - (int) ($t * 0.7), $hy], $dark);
                    imagefilledellipse($im, $hx, $hy - (int) ($r * 0.2), (int) ($r * 0.8), (int) ($r * 1.15), $dark);
                }
            }
            $x += mt_rand((int) ($r * 2.2), (int) ($r * 3.4));
        }
        imagefilledrectangle($im, 0, $H - 70, $W, $H, $dark);
    }

    protected static function candle(GdImage $im, int $x, int $base, int $height): void
    {
        $body = self::col($im, [250, 236, 214], 10);
        $w = (int) ($height * 0.32);
        imagefilledrectangle($im, $x - $w, $base - $height, $x + $w, $base + 200, $body);
        self::glow($im, $x, $base - $height - 34, (int) ($height * 1.6), [255, 196, 110], 0.55);
        imagefilledellipse($im, $x, $base - $height - 30, (int) ($w * 0.9), (int) ($w * 2.4), self::col($im, [255, 222, 150]));
        imagefilledellipse($im, $x, $base - $height - 22, (int) ($w * 0.45), (int) ($w * 1.2), self::col($im, [255, 255, 235]));
    }

    protected static function birds(GdImage $im, int $W, int $H, int $count): void
    {
        $c = self::col($im, [30, 10, 25], 30);
        imagesetthickness($im, 4);
        for ($i = 0; $i < $count; $i++) {
            $x = mt_rand((int) ($W * 0.2), (int) ($W * 0.8));
            $y = mt_rand((int) ($H * 0.2), (int) ($H * 0.45));
            $s = mt_rand(14, 26);
            imagearc($im, $x - $s, $y, $s * 2, $s, 200, 340, $c);
            imagearc($im, $x + $s, $y, $s * 2, $s, 200, 340, $c);
        }
        imagesetthickness($im, 1);
    }

    protected static function stars(GdImage $im, int $W, int $H, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $x = mt_rand(0, $W);
            $y = mt_rand(0, $H);
            $size = mt_rand(1, 100) > 92 ? mt_rand(4, 7) : mt_rand(1, 3);
            imagefilledellipse($im, $x, $y, $size, $size, self::col($im, [255, 255, 255], mt_rand(10, 70)));
            if ($size > 3) {
                self::glow($im, $x, $y, $size * 4, [220, 230, 255], 0.35);
            }
        }
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2: int}>  $palette
     */
    protected static function bokeh(GdImage $im, int $W, int $H, int $count, array $palette, float $minOpacity, float $maxOpacity): void
    {
        for ($i = 0; $i < $count; $i++) {
            $x = mt_rand(0, $W);
            $y = mt_rand(0, $H);
            $r = mt_rand(10, 70);
            $opacity = $minOpacity + (mt_rand(0, 100) / 100) * ($maxOpacity - $minOpacity) * (1 - $r / 90);
            $color = $palette[array_rand($palette)];
            imagefilledellipse($im, $x, $y, $r * 2, $r * 2, self::col($im, $color, (int) (127 * (1 - $opacity))));
            imagefilledellipse($im, $x, $y, (int) ($r * 1.7), (int) ($r * 1.7), self::col($im, $color, (int) (127 * (1 - $opacity * 0.5))));
        }
    }

    protected static function rays(GdImage $im, int $ox, int $oy, int $count, array $color, float $opacity, float $length): void
    {
        $c = self::col($im, $color, (int) (127 * (1 - $opacity)));
        for ($i = 0; $i < $count; $i++) {
            $a = -M_PI + $i * (M_PI / $count) + mt_rand(-5, 5) / 100;
            $spread = 0.035 + mt_rand(0, 3) / 100;
            imagefilledpolygon($im, [
                $ox, $oy,
                (int) ($ox + $length * cos($a - $spread)), (int) ($oy + $length * sin($a - $spread)),
                (int) ($ox + $length * cos($a + $spread)), (int) ($oy + $length * sin($a + $spread)),
            ], $c);
        }
    }

    protected static function beam(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $width, array $color, float $opacity): void
    {
        for ($i = 4; $i >= 1; $i--) {
            $w = (int) ($width * $i / 4);
            $c = self::col($im, $color, (int) (127 * (1 - $opacity / 2.2)));
            imagefilledpolygon($im, [$x1 - 6, $y1, $x1 + 6, $y1, $x2 + $w, $y2, $x2 - $w, $y2], $c);
        }
    }

    /** @var array<string, GdImage> */
    protected static array $sprites = [];

    /**
     * Soft radial glow: a smooth per-pixel sprite, scaled with resampling
     * (no banding rings).
     */
    protected static function glow(GdImage $im, int $cx, int $cy, int $radius, array $color, float $strength): void
    {
        $key = implode(',', $color).'|'.round($strength, 2);
        $sprite = self::$sprites[$key] ??= self::glowSprite($color, $strength);
        $d = max(2, 2 * $radius);
        imagecopyresampled($im, $sprite, $cx - $radius, $cy - $radius, 0, 0, $d, $d, 256, 256);
    }

    protected static function glowSprite(array $color, float $strength): GdImage
    {
        $s = 256;
        $sprite = imagecreatetruecolor($s, $s);
        imagealphablending($sprite, false);
        imagesavealpha($sprite, true);
        $half = $s / 2;
        for ($y = 0; $y < $s; $y++) {
            for ($x = 0; $x < $s; $x++) {
                $d = min(1, sqrt(($x - $half) ** 2 + ($y - $half) ** 2) / $half);
                $a = $strength * (1 - $d * $d) ** 2;
                imagesetpixel($sprite, $x, $y, imagecolorallocatealpha($sprite, $color[0], $color[1], $color[2], 127 - (int) round(127 * $a)));
            }
        }

        return $sprite;
    }

    protected static function bottomShade(GdImage $im, int $W, int $H, float $strength): void
    {
        $from = (int) ($H * 0.55);
        for ($y = $from; $y < $H; $y++) {
            $t = ($y - $from) / max(1, $H - $from);
            $a = (int) (127 - 127 * $strength * $t * $t);
            imageline($im, 0, $y, $W, $y, self::col($im, [8, 2, 6], max(0, min(127, $a))));
        }
    }

    /**
     * @param  array<int, array{0: float, 1: array{0: int, 1: int, 2: int}}>  $stops
     */
    protected static function vgradient(GdImage $im, array $stops): void
    {
        $W = imagesx($im);
        $H = imagesy($im);
        for ($y = 0; $y < $H; $y++) {
            $t = $y / max(1, $H - 1);
            for ($i = 0; $i < count($stops) - 1; $i++) {
                [$p1, $c1] = $stops[$i];
                [$p2, $c2] = $stops[$i + 1];
                if ($t >= $p1 && $t <= $p2) {
                    $local = ($t - $p1) / max(0.0001, $p2 - $p1);
                    // Smoothstep for softer bands.
                    $local = $local * $local * (3 - 2 * $local);
                    imageline($im, 0, $y, $W, $y, self::col($im, self::mix($c1, $c2, $local)));
                    break;
                }
            }
        }
    }

    protected static function centeredText(GdImage $im, string $text, int $size, float $cx, int $baseline, array $rgb, int $shadow = 0, int $tracking = 0): void
    {
        $font = self::font();
        if (! $font || $text === '') {
            return;
        }
        if ($tracking > 0) {
            // Letter-spacing with thin spaces (small caps style for the church name).
            $text = implode("\u{2009}", mb_str_split($text));
        }
        $box = imagettfbbox($size, 0, $font, $text);
        $width = $box[2] - $box[0];
        $x = (int) ($cx - $width / 2);
        if ($shadow) {
            imagettftext($im, $size, 0, $x + $shadow, $baseline + $shadow, self::col($im, [0, 0, 0], 95), $font, $text);
        }
        imagettftext($im, $size, 0, $x, $baseline, self::col($im, $rgb), $font, $text);
    }

    /* ── Utilities ────────────────────────────────────────────────────── */

    protected static function canvas(int $w, int $h): GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, true);

        return $im;
    }

    protected static function save(GdImage $im, string $path, int $w, int $h, string $format): string
    {
        $out = imagecreatetruecolor($w, $h);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, imagesx($im), imagesy($im));
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $format === 'png' ? imagepng($out, $path, 6) : imagejpeg($out, $path, 86);
        imagedestroy($im);
        imagedestroy($out);

        return $path;
    }

    protected static function col(GdImage $im, array $rgb, int $alpha = 0): int
    {
        return imagecolorallocatealpha($im, max(0, min(255, (int) $rgb[0])), max(0, min(255, (int) $rgb[1])), max(0, min(255, (int) $rgb[2])), $alpha);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    protected static function mix(array $a, array $b, float $t): array
    {
        return [(int) ($a[0] + ($b[0] - $a[0]) * $t), (int) ($a[1] + ($b[1] - $a[1]) * $t), (int) ($a[2] + ($b[2] - $a[2]) * $t)];
    }

    protected static function initials(string $name): string
    {
        $words = array_values(array_filter(preg_split('/\s+/', preg_replace('/[^\p{L}\s]/u', '', $name)), fn ($w) => ! in_array(mb_strtolower($w), ['of', 'the', 'and', 'church', 'st'], true)));

        return mb_strtoupper(mb_substr($words[0] ?? 'F', 0, 1).mb_substr($words[1] ?? '', 0, 1));
    }

    protected static function shortName(string $name): string
    {
        $words = preg_split('/\s+/', $name);

        return implode(' ', array_slice($words, 0, 3));
    }

    protected static function font(): ?string
    {
        if (self::$font === null) {
            $candidates = [base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'), 'C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'];
            self::$font = collect($candidates)->first(fn ($f) => is_file($f)) ?? '';
        }

        return self::$font ?: null;
    }
}
