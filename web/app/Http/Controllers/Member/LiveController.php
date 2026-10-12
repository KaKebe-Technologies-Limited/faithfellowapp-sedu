<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\LiveChatMessage;
use App\Models\LiveStream;
use App\Services\StreamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LiveController extends Controller
{
    public function __construct(protected StreamingService $streaming) {}

    public function index(Request $request): View
    {
        $churchIds = $request->user()->memberChurchIds();

        return view('member.live.index', [
            'live' => LiveStream::with('church.media')->forChurches($churchIds)->live()->latest('started_at')->get(),
            'upcoming' => LiveStream::with('church.media')->forChurches($churchIds)->where('status', 'scheduled')
                ->where('scheduled_at', '>=', now()->subHour())->orderBy('scheduled_at')->take(12)->get(),
            'recent' => LiveStream::with(['church.media', 'sermon'])->forChurches($churchIds)->whereIn('status', ['ended', 'archived'])
                ->latest('ended_at')->take(8)->get(),
        ]);
    }

    public function show(Request $request, LiveStream $liveStream): View
    {
        $this->authorize('view', $liveStream);
        $liveStream->load(['church.media', 'sermon']);

        return view('member.live.show', [
            'stream' => $liveStream,
            'playback' => $this->streaming->playback($liveStream),
            'viewers' => $this->streaming->currentViewers($liveStream),
            'canModerate' => $request->user()?->canInChurch($liveStream->church_id, 'live.manage') ?? false,
        ]);
    }

    public function heartbeat(Request $request, LiveStream $liveStream): JsonResponse
    {
        $viewerKey = $request->session()->get('live_viewer_key') ?? tap(Str::random(32), fn ($k) => $request->session()->put('live_viewer_key', $k));

        $viewers = $liveStream->isLive()
            ? $this->streaming->heartbeat($liveStream, $viewerKey, $request->user()?->id, $request->boolean('audio'))
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
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->user?->name ?? __('Guest'), 'body' => $m->body, 'at' => $m->created_at?->format('H:i')]);

        $hidden = $after > 0
            ? LiveChatMessage::where('live_stream_id', $liveStream->id)->where('is_hidden', true)->where('id', '>', max(0, $after - 200))->pluck('id')
            : [];

        return response()->json(['messages' => $messages, 'hidden' => $hidden]);
    }

    public function postMessage(Request $request, LiveStream $liveStream): JsonResponse
    {
        $this->authorize('view', $liveStream);
        abort_unless($liveStream->chat_enabled && $liveStream->isLive(), 422, __('Chat is closed.'));
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);

        LiveChatMessage::create([
            'church_id' => $liveStream->church_id,
            'live_stream_id' => $liveStream->id,
            'user_id' => $request->user()->id,
            'body' => strip_tags($data['body']),
        ]);

        return response()->json(['ok' => true]);
    }
}
