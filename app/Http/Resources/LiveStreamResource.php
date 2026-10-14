<?php

namespace App\Http\Resources;

use App\Models\LiveStream;
use App\Services\StreamingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LiveStream */
class LiveStreamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $request->routeIs('api.v1.live.show');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'audio_only_available' => (bool) $this->audio_only_available,
            'church' => new ChurchSummaryResource($this->whenLoaded('church')),
            'watch_url' => route('live.show', $this->resource),

            // Detail-only: what the player and chat need. `playback.embed` is a YouTube/Facebook page (shown in a
            // small in-app player); `playback.video` / `audio` are HLS playlists the native player plays directly.
            'description' => $this->when($detail, fn () => plain_text($this->description)),
            'chat_enabled' => $this->when($detail, fn () => (bool) $this->chat_enabled),
            'playback' => $this->when($detail, fn () => $this->isLive() ? app(StreamingService::class)->playback($this->resource) : ['video' => null, 'audio' => null, 'embed' => null]),
            'viewers' => $this->when($detail, fn () => app(StreamingService::class)->currentViewers($this->resource)),
            'recording' => $this->when($detail, fn () => ($this->recording_url || $this->sermon) ? ['url' => $this->recording_url, 'sermon_id' => $this->sermon?->id, 'title' => $this->sermon?->title] : null),
        ];
    }
}
