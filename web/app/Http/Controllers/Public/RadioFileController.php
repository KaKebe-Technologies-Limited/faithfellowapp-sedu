<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\RadioTrack;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams the radio's songs and covers. Songs answer byte-range requests (the response class handles the Range
 * header), so a phone can join a song part-way through, just as the station's clock says.
 */
class RadioFileController extends Controller
{
    public function stream(RadioTrack $track): BinaryFileResponse
    {
        abort_unless(is_file($track->path()), 404);

        return response()->file($track->path(), [
            'Content-Type' => $track->mime,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function cover(RadioTrack $track): BinaryFileResponse
    {
        abort_unless($file = $track->coverFile(), 404);

        return response()->file($file, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
