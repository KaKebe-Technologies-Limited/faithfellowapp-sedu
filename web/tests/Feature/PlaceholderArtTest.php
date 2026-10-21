<?php

use App\Support\ChurchArtwork;
use App\Support\ContentArtwork;

/*
| Anything without a photo of its own (events, sermons, radio tracks, podcasts, church banners…) gets the FaithFellows placeholder: the white logo mark
| in the middle of a soft gradient in the church's colour.
*/

function placeholderPixels(string $path, int $x0, int $y0, int $x1, int $y1): array
{
    $im = imagecreatefromjpeg($path);
    $bright = 0;
    $total = 0;
    $r = $g = $b = 0;
    for ($y = $y0; $y < $y1; $y += 3) {
        for ($x = $x0; $x < $x1; $x += 3) {
            $c = imagecolorat($im, $x, $y);
            [$cr, $cg, $cb] = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
            $bright += ($cr > 235 && $cg > 235 && $cb > 235) ? 1 : 0;
            [$r, $g, $b] = [$r + $cr, $g + $cg, $b + $cb];
            $total++;
        }
    }

    return ['bright' => $bright, 'avg' => [intdiv($r, $total), intdiv($g, $total), intdiv($b, $total)]];
}

test('a cover without a photo is the FaithFellows mark on a gradient in the church colour', function () {
    $path = sys_get_temp_dir().'/ff-placeholder-'.uniqid().'.jpg';
    ContentArtwork::cover($path, 'event', 'Youth Camp 2026', '#BC2649', 3);

    expect(getimagesize($path)[0])->toBe(1200)->and(getimagesize($path)[1])->toBe(675);
    $middle = placeholderPixels($path, 480, 220, 720, 450);   // where the white mark is
    $corner = placeholderPixels($path, 20, 20, 200, 120);     // plain gradient
    // The mark is white and in the middle; the rest is the brand colour (red leads over green and blue).
    expect($middle['bright'])->toBeGreaterThan(150)->and($corner['bright'])->toBe(0)->and($corner['avg'][0])->toBeGreaterThan($corner['avg'][1] + 30);
    unlink($path);
});

test('the placeholder takes the colour of the church and looks different from item to item', function () {
    $red = sys_get_temp_dir().'/ff-ph-red-'.uniqid().'.jpg';
    $teal = sys_get_temp_dir().'/ff-ph-teal-'.uniqid().'.jpg';
    $other = sys_get_temp_dir().'/ff-ph-other-'.uniqid().'.jpg';
    ContentArtwork::cover($red, 'sermon', 'x', '#BC2649', 1);
    ContentArtwork::cover($teal, 'sermon', 'x', '#1E7F74', 1);
    ContentArtwork::cover($other, 'sermon', 'x', '#BC2649', 2);

    $teal = placeholderPixels($teal, 20, 20, 200, 120)['avg'];
    expect($teal[1])->toBeGreaterThan($teal[0] + 20)                                    // teal: green leads
        ->and(md5_file($red))->not->toBe(md5_file($other));                              // another seed, another look
    array_map('unlink', [$red, $other]);
});

test('a church banner without a photo uses the same placeholder', function () {
    $path = sys_get_temp_dir().'/ff-banner-'.uniqid().'.jpg';
    ChurchArtwork::banner($path, '#BC2649', 4);

    expect(getimagesize($path)[0])->toBe(1600)->and(getimagesize($path)[1])->toBe(600)
        ->and(placeholderPixels($path, 650, 150, 950, 330)['bright'])->toBeGreaterThan(100);
    unlink($path);
});

test('the pictures are served from the art address and redrawn when the drawing changes', function () {
    expect(ContentArtwork::VERSION)->toBeGreaterThanOrEqual(2);
    $this->get(route('art', ['kind' => 'event', 'id' => 999999]))->assertNotFound();
});
