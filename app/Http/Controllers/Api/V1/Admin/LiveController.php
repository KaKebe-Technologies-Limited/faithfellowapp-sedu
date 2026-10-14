<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\LiveStream;
use App\Services\StreamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Live services from the phone: schedule one, go live and end it. Streaming itself comes from the church's
 * encoder (or a YouTube / Facebook / HLS link); members watch in the app.
 */
class LiveController extends Controller
{
    public function __construct(protected StreamingService $streaming) {}

    public function index(Church $church): JsonResponse
    {
        return response()->json(['data' => $church->liveStreams()->latest('scheduled_at')->take(30)->get()->map(fn (LiveStream $s) => $this->card($s))->values()]);
    }

    public function store(Request $request, Church $church): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['required', 'date'],
            'source_type' => ['required', Rule::in(['rtmp', 'youtube', 'facebook', 'hls'])],
            'external_url' => ['nullable', 'required_unless:source_type,rtmp', 'url', 'max:500'],
            'chat_enabled' => ['boolean'],
        ]);

        $stream = $church->liveStreams()->create([...$data, 'user_id' => $request->user()->id, 'status' => 'scheduled']);
        if ($stream->source_type === 'rtmp') {
            $this->streaming->rotateKey($stream);
        }

        return response()->json(['data' => $this->card($stream->fresh())], 201);
    }

    public function start(Church $church, LiveStream $liveStream): JsonResponse
    {
        $this->streaming->start($liveStream);

        return response()->json(['data' => $this->card($liveStream->fresh())]);
    }

    public function stop(Church $church, LiveStream $liveStream): JsonResponse
    {
        $this->streaming->stop($liveStream);

        return response()->json(['data' => $this->card($liveStream->fresh())]);
    }

    /** @return array<string, mixed> */
    protected function card(LiveStream $s): array
    {
        return [
            'id' => $s->id, 'title' => $s->title, 'status' => $s->status, 'source_type' => $s->source_type,
            'scheduled_at' => $s->scheduled_at?->toIso8601String(), 'started_at' => $s->started_at?->toIso8601String(),
            'viewers' => $s->isLive() ? $this->streaming->currentViewers($s) : null, 'peak_viewers' => (int) $s->peak_viewers,
        ];
    }
}
