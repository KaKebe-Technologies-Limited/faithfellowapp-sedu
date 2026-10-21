<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Devotional;
use App\Models\Event;
use App\Models\Podcast;
use App\Models\RadioTrack;
use App\Models\Sermon;
use App\Models\SmallGroup;
use App\Support\ContentArtwork;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the generated cover picture of an event, sermon, devotional or campaign that has no photo of its own.
 * The picture carries no text and reveals nothing but a scene, and is drawn once and then served from disk.
 */
class ArtController extends Controller
{
    public const KINDS = ['event' => Event::class, 'sermon' => Sermon::class, 'devotional' => Devotional::class, 'campaign' => Campaign::class, 'group' => SmallGroup::class, 'radio' => RadioTrack::class, 'podcast' => Podcast::class];

    public function show(string $kind, int $id): BinaryFileResponse
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        $model = in_array($kind, ['radio', 'podcast'], true) ? self::KINDS[$kind]::findOrFail($id) : self::KINDS[$kind]::with('church')->findOrFail($id);

        // The address changes with the title and colour, so a long cache is safe.
        return response()->file(ContentArtwork::file($kind, $model), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
