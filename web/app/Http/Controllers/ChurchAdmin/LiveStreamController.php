<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\LiveChatMessage;
use App\Models\LiveStream;
use App\Services\StreamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LiveStreamController extends Controller
{
    public function __construct(protected StreamingService $streaming) {}

    public function index(Church $church): View
    {
        return view('church-admin.live.index', [
            'church' => $church,
            'streams' => $church->liveStreams()->with('sermon')->orderByRaw("FIELD(status, 'live', 'scheduled', 'ended', 'archived')")
                ->orderByDesc('scheduled_at')->paginate(20),
            'usage' => $this->streaming->monthlyUsage($church),
        ]);
    }

    public function create(Church $church): View
    {
        return view('church-admin.live.form', ['church' => $church, 'stream' => new LiveStream([
            'title' => __('Sunday Service'), 'scheduled_at' => now()->next('Sunday')->setTime(10, 0), 'source_type' => 'rtmp', 'chat_enabled' => true,
        ])]);
    }

    public function store(Request $request, Church $church): RedirectResponse
    {
        $stream = $church->liveStreams()->create([...$this->validated($request, $church), 'user_id' => $request->user()->id, 'status' => 'scheduled']);
        if ($stream->source_type === 'rtmp') {
            $this->streaming->rotateKey($stream);
        }

        return redirect()->route('church-admin.live.show', [$church, $stream])->with('success', __('Service scheduled. Use the stream key below in OBS or Larix.'));
    }

    /**
     * Control room: stream key, go live / end, viewers, chat moderation.
     */
    public function show(Church $church, LiveStream $liveStream): View
    {
        return view('church-admin.live.show', [
            'church' => $church,
            'stream' => $liveStream,
            'ingest' => $this->streaming->ingestUrl(),
            'playback' => $this->streaming->playback($liveStream),
            'viewers' => $this->streaming->currentViewers($liveStream),
        ]);
    }

    public function edit(Church $church, LiveStream $liveStream): View
    {
        return view('church-admin.live.form', ['church' => $church, 'stream' => $liveStream]);
    }

    public function update(Request $request, Church $church, LiveStream $liveStream): RedirectResponse
    {
        $liveStream->update($this->validated($request, $church));
        if ($liveStream->source_type === 'rtmp' && ! $liveStream->stream_key) {
            $this->streaming->rotateKey($liveStream);
        }

        return redirect()->route('church-admin.live.show', [$church, $liveStream])->with('success', __('Stream updated.'));
    }

    public function start(Church $church, LiveStream $liveStream): RedirectResponse
    {
        $this->streaming->start($liveStream);

        return back()->with('success', __('You are live! Subscribers are being notified.'));
    }

    public function stop(Church $church, LiveStream $liveStream): RedirectResponse
    {
        $this->streaming->stop($liveStream);

        return back()->with('success', __('Stream ended. You can now publish the recording as a sermon.'));
    }

    public function archive(Request $request, Church $church, LiveStream $liveStream): RedirectResponse
    {
        $data = $request->validate([
            'recording_url' => ['nullable', 'url', 'max:500'],
            'title' => ['nullable', 'string', 'max:200'],
            'preacher' => ['nullable', 'string', 'max:120'],
            'scripture' => ['nullable', 'string', 'max:160'],
        ]);
        if (! empty($data['recording_url'])) {
            $liveStream->update(['recording_url' => $data['recording_url']]);
        }
        $sermon = $this->streaming->archiveAsSermon($liveStream, $data);

        return redirect()->route('church-admin.sermons.edit', [$church, $sermon])->with('success', __('Recording archived as a sermon.'));
    }

    public function rotateKey(Church $church, LiveStream $liveStream): RedirectResponse
    {
        $this->streaming->rotateKey($liveStream);

        return back()->with('success', __('New stream key generated. Update it in your streaming software.'));
    }

    public function destroy(Church $church, LiveStream $liveStream): RedirectResponse
    {
        abort_if($liveStream->isLive(), 422, __('End the stream before deleting it.'));
        $liveStream->delete();

        return redirect()->route('church-admin.live.index', $church)->with('success', __('Stream deleted.'));
    }

    public function stats(Church $church, LiveStream $liveStream): JsonResponse
    {
        return response()->json([
            'status' => $liveStream->fresh()->status,
            'viewers' => $this->streaming->currentViewers($liveStream),
            'peak' => $liveStream->fresh()->peak_viewers,
        ]);
    }

    public function hideMessage(Church $church, LiveStream $liveStream, int $messageId): JsonResponse
    {
        LiveChatMessage::where('live_stream_id', $liveStream->id)->whereKey($messageId)->update(['is_hidden' => true]);

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, Church $church): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['required', 'date'],
            'source_type' => ['required', Rule::in(['rtmp', 'youtube', 'facebook', 'hls'])],
            'external_url' => ['nullable', 'required_unless:source_type,rtmp', 'url', 'max:500'],
            'chat_enabled' => ['boolean'],
            'audio_only_available' => ['boolean'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('church_id', $church->id)],
            'event_id' => ['nullable', Rule::exists('events', 'id')->where('church_id', $church->id)],
        ]);
    }
}
