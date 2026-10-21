<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Cover pictures for everything that has no photo of its own (events, sermons, devotionals, campaigns, groups, radio tracks, podcasts), so the
 * app is never a wall of text or a grey box: the FaithFellows logo on a soft background in the church's brand colour (ChurchArtwork::placeholder).
 * No text is drawn on the picture: the app puts the title on top. Churches replace it simply by uploading a real photo.
 *
 * `themeFor()` still says which scene a title would suggest (a youth camp, a prayer night…); the welcome-screen posters use scenes.
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
        'group' => ['book', 'waves', 'bokeh', 'sunrise'],
        'radio' => ['worship', 'bokeh', 'sunrise', 'waves'],
        'podcast' => ['sunrise', 'book', 'bokeh', 'worship', 'waves'],
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
    public const VERSION = 4;

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

    /**
     * A 16:9 cover picture (JPEG): the FaithFellows placeholder in the church's colour. Items without a photo used to get a scene that fitted
     * their title; now they all get the logo placeholder, and the seed (the item and its kind) gives neighbours different looks.
     */
    public static function cover(string $path, string $kind, string $title, string $hex = '#BC2649', int $seed = 1, ?string $hint = null, int $w = 1200, int $h = 675): string
    {
        $layout = match ($kind) {
            'radio', 'podcast' => 'center', // shown as squares, with nothing over them
            'sermon' => 'compact',          // the app puts a play button in the middle
            default => 'top',               // a title or a date sits over the lower half
        };

        return self::placeholder($path, $hex, $seed + (crc32($kind) % 4), $w, $h, 'jpg', $layout);
    }

    /** A tall poster (JPEG) of one scene, for the app's welcome screen. */
    public static function poster(string $path, string $theme, string $hex = '#BC2649', int $seed = 1, int $w = 900, int $h = 1600): string
    {
        return self::scene($path, $theme, $hex, $seed, $w, $h);
    }
}
