<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RadioService;
use Illuminate\Http\JsonResponse;

/**
 * The gospel radio in the app: what is playing right now (and how far into the song), what comes next and what
 * was just played. The app plays the song from `offset` seconds and keeps going through `upcoming`.
 */
class RadioController extends Controller
{
    public function show(RadioService $radio): JsonResponse
    {
        $now = $radio->nowPlaying();

        return response()->json(['data' => [
            'enabled' => $now !== null,
            'name' => $radio->name(),
            'tagline' => __('Gospel music, day and night'),
            'now' => $now,
            'songs' => $radio->tracks()->count(),
            'server_time' => now()->toIso8601String(),
        ]]);
    }
}
