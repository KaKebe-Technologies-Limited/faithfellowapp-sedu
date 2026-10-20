<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RadioStation;
use App\Services\RadioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The radio in the app: what is playing right now on a channel (and how far into it), what comes next and what was just played. The app plays
 * the track from `offset` seconds and keeps going through `upcoming`. `GET /radio` is the Gospel radio (as it always was); `GET /radio/stations`
 * lists every channel the person can hear (Gospel radio, Podcast radio, and the channels of their churches); `GET /radio/stations/{slug}` is one
 * channel's "on air" information.
 */
class RadioController extends Controller
{
    public function show(RadioService $radio): JsonResponse
    {
        return response()->json(['data' => $radio->payload(RadioStation::gospel())]);
    }

    public function stations(Request $request, RadioService $radio): JsonResponse
    {
        return response()->json(['data' => $radio->stationsFor($request->user())->map(fn (RadioStation $s) => [
            'id' => $s->id,
            'slug' => $s->slug,
            'kind' => $s->kind,
            'name' => $radio->name($s),
            'tagline' => $s->tagline(),
            'church' => $s->church_id && $s->church ? ['id' => $s->church->id, 'name' => $s->church->name] : null,
            'songs' => $radio->tracks($s)->count(),
        ])->values()]);
    }

    public function station(Request $request, RadioService $radio, string $slug): JsonResponse
    {
        $station = match (true) {
            $slug === RadioStation::GOSPEL => RadioStation::gospel(),
            $slug === RadioStation::PODCAST && RadioStation::ready() => RadioStation::podcast(),
            RadioStation::ready() => RadioStation::with('church')->where('slug', $slug)->whereNotNull('church_id')->first(),
            default => null,
        };
        abort_unless($station && $radio->canListen($request->user(), $station), 404);

        return response()->json(['data' => $radio->payload($station)]);
    }
}
