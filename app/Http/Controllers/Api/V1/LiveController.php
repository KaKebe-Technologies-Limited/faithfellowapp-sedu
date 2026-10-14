<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LiveStreamResource;
use App\Models\LiveChatMessage;
use App\Models\LiveStream;
use App\Rules\NoContactDetails;
use App\Services\StreamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Watching a church service in the app: the stream's playback sources, the viewer heartbeat and the live chat.
 */
class LiveController extends Controller
{
    public function __construct(protected StreamingService $streaming) {}

    public function show(LiveStream $liveStream): LiveStreamResource
    {
        $this->authorize('view', $liveStream);

        return new LiveStreamResource($liveStream->load(['church.media', 'sermon']));
    }

    /** Called every ~30 seconds while someone watches; keeps the viewer count honest. */
    public function heartbeat(Request $request, LiveStream $liveStream): JsonResponse
    {
        $this->authorize('view', $liveStream);

        $viewers = $liveStream->isLive()
            ? $this->streaming->heartbeat($liveStream, 'u'.$request->user()->id, $request->user()->id, $request->boolean('audio'))
            : 0;

        return response()->json(['viewers' => $viewers, 'status' => $liveStream->status]);
    }

    public function messages(Request $request, LiveStream $liveStream): JsonResponse
    {
        $this->authorize('view', $liveStream);
        $after = (int) $request->query('after', 0);

        $messages = LiveChatMessage::with('user:id,name')
            ->where('live_stream_id', $liveStream->id)->where('is_hidden', false)
            ->where('id', '>', $after)->orderBy('id')->take(100)->get()
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->user?->name ?? __('Guest'), 'body' => $m->body, 'at' => $m->created_at?->toIso8601String(), 'mine' => $m->user_id === $request->user()->id]);

        $hidden = $after > 0
            ? LiveChatMessage::where('live_stream_id', $liveStream->id)->where('is_hidden', true)->where('id', '>', max(0, $after - 200))->pluck('id')
            : [];

        return response()->json(['data' => $messages, 'hidden' => $hidden, 'status' => $liveStream->status]);
    }

    public function postMessage(Request $request, LiveStream $liveStream): JsonResponse
    {
        $this->authorize('view', $liveStream);
        abort_unless($liveStream->chat_enabled && $liveStream->isLive(), 422, __('Chat is closed.'));
        $data = $request->validate(['body' => ['required', 'string', 'max:500', new NoContactDetails]]);

        $message = LiveChatMessage::create([
            'church_id' => $liveStream->church_id,
            'live_stream_id' => $liveStream->id,
            'user_id' => $request->user()->id,
            'body' => strip_tags($data['body']),
        ]);

        return response()->json(['data' => ['id' => $message->id, 'name' => $request->user()->name, 'body' => $message->body, 'at' => $message->created_at?->toIso8601String(), 'mine' => true]], 201);
    }
}
