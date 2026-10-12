<?php
/**
 * Generate branded SVG placeholder images for the homepage collage.
 * Run: C:\xampp\php\php.exe scripts/make-placeholder-images.php
 */

$out = __DIR__ . '/../public/assets/images';
if (!is_dir($out)) mkdir($out, 0755, true);

$images = [
    'hero-worship'    => ['🎵', '#BC2649', '#D34268', 'Worship'],
    'hero-community'  => ['🤝', '#8E1C37', '#BC2649', 'Community'],
    'hero-giving'     => ['💝', '#D34268', '#F27EA8', 'Giving'],
    'hero-prayer'     => ['🙏', '#F27EA8', '#F7B2D1', 'Prayer'],
    'family-church'   => ['⛪', '#BC2649', '#D34268', 'Family'],
    'why-us'          => ['✨', '#8E1C37', '#BC2649', 'Ministry'],
];

foreach ($images as $name => [$emoji, $c1, $c2, $label]) {
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
  <defs>
    <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$c1}"/>
      <stop offset="100%" style="stop-color:{$c2}"/>
    </linearGradient>
  </defs>
  <rect width="800" height="600" fill="url(#g)"/>
  <text x="400" y="270" font-size="120" text-anchor="middle" dominant-baseline="middle">{$emoji}</text>
  <text x="400" y="380" font-family="Arial,sans-serif" font-size="36" font-weight="bold"
        fill="rgba(255,255,255,0.9)" text-anchor="middle">{$label}</text>
  <text x="400" y="425" font-family="Arial,sans-serif" font-size="18"
        fill="rgba(255,255,255,0.6)" text-anchor="middle">FaithFellows</text>
</svg>
SVG;
    file_put_contents("{$out}/{$name}.svg", $svg);
    echo "Created: {$name}.svg\n";
}

echo "\nDone! Place real photos at public/assets/images/ to replace these placeholders.\n";
