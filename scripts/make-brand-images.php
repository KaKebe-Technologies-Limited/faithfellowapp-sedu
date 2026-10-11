<?php

// Renders FaithFellows brand PNGs with GD (supersampled for anti-aliasing).
$public = $argv[1];
$fontBold = $argv[2];
$fontRegular = $argv[3];

function hexcolor($im, $hex, $alpha = 0)
{
    [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

    return imagecolorallocatealpha($im, $r, $g, $b, $alpha);
}

function roundedRect($im, $x1, $y1, $x2, $y2, $r, $color)
{
    imagefilledrectangle($im, (int) ($x1 + $r), (int) $y1, (int) ($x2 - $r), (int) $y2, $color);
    imagefilledrectangle($im, (int) $x1, (int) ($y1 + $r), (int) $x2, (int) ($y2 - $r), $color);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
        imagefilledellipse($im, (int) $cx, (int) $cy, (int) (2 * $r), (int) (2 * $r), $color);
    }
}

/** Draws the filled mark (magenta tile + white glyph) into $im at offset/size. */
function drawMark($im, $ox, $oy, $size, $bg, $fg, $radius = 30)
{
    $s = $size / 100;
    roundedRect($im, $ox, $oy, $ox + 100 * $s, $oy + 100 * $s, $radius * $s, $bg);
    // Arc: quarter annulus centred at (79.6, 21.7), radii 24.8–35.0, lower-left quadrant (90°..180°).
    $cx = $ox + 79.6 * $s;
    $cy = $oy + 21.7 * $s;
    imagefilledarc($im, (int) $cx, (int) $cy, (int) (70.0 * $s), (int) (70.0 * $s), 90, 180, $fg, IMG_ARC_PIE);
    imagefilledarc($im, (int) $cx, (int) $cy, (int) (49.6 * $s), (int) (49.6 * $s), 89, 181, $bg, IMG_ARC_PIE);
    // Cross bars.
    imagefilledrectangle($im, (int) ($ox + 22.3 * $s), (int) ($oy + 46.5 * $s), (int) ($ox + 55.6 * $s), (int) ($oy + 56.7 * $s), $fg);
    imagefilledrectangle($im, (int) ($ox + 45.4 * $s), (int) ($oy + 46.5 * $s), (int) ($ox + 55.6 * $s), (int) ($oy + 78.5 * $s), $fg);
}

function icon($size, $file, $padding = 0)
{
    $ss = 4;
    $big = imagecreatetruecolor($size * $ss, $size * $ss);
    imagesavealpha($big, true);
    imagealphablending($big, false);
    imagefill($big, 0, 0, imagecolorallocatealpha($big, 0, 0, 0, 127));
    imagealphablending($big, true);
    $pad = $padding * $size * $ss;
    drawMark($big, $pad, $pad, $size * $ss - 2 * $pad, hexcolor($big, '#BC2649'), hexcolor($big, '#FFFFFF'), $padding ? 22 : 30);
    $out = imagecreatetruecolor($size, $size);
    imagesavealpha($out, true);
    imagealphablending($out, false);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $big, 0, 0, 0, 0, $size, $size, $size * $ss, $size * $ss);
    imagepng($out, $file, 9);
}

@mkdir("$public/images", 0777, true);
icon(192, "$public/images/icon-192.png");
icon(512, "$public/images/icon-512.png");
icon(512, "$public/images/icon-maskable-512.png");
icon(32, "$public/images/icon-32.png");

// favicon.ico embedding a 32px PNG.
$png = file_get_contents("$public/images/icon-32.png");
$ico = pack('vvv', 0, 1, 1).pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22).$png;
file_put_contents("$public/favicon.ico", $ico);

// Open Graph image 1200×630.
$w = 1200;
$h = 630;
$ss = 2;
$im = imagecreatetruecolor($w * $ss, $h * $ss);
for ($x = 0; $x < $w * $ss; $x++) {
    $t = $x / ($w * $ss);
    $c = imagecolorallocate($im, (int) (188 + (211 - 188) * $t), (int) (38 + (66 - 38) * $t), (int) (73 + (104 - 73) * $t));
    imageline($im, $x, 0, $x, $h * $ss, $c);
}
imagealphablending($im, true);
$white = hexcolor($im, '#FFFFFF');
$soft = imagecolorallocatealpha($im, 255, 255, 255, 115);
drawMark($im, 90 * $ss, 90 * $ss, 120 * $ss, $white, hexcolor($im, '#BC2649'));
imagettftext($im, 34 * $ss, 0, 230 * $ss, 168 * $ss, $white, $fontBold, 'FaithFellows');
imagettftext($im, 54 * $ss, 0, 90 * $ss, 340 * $ss, $white, $fontBold, 'Empowering Your Ministry');
imagettftext($im, 54 * $ss, 0, 90 * $ss, 420 * $ss, $white, $fontBold, 'in the Digital Age');
imagettftext($im, 24 * $ss, 0, 90 * $ss, 520 * $ss, $white, $fontRegular, 'Websites · Sermons · Live · Giving · Prayer — one platform');
$out = imagecreatetruecolor($w, $h);
imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, $w * $ss, $h * $ss);
imagepng($out, "$public/images/og-image.png", 9);

echo "ok\n";
