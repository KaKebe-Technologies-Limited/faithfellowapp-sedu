<?php

namespace App\Http\Resources;

use App\Models\LiveStream;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LiveStream */
class LiveStreamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'audio_only_available' => (bool) $this->audio_only_available,
            'church' => new ChurchSummaryResource($this->whenLoaded('church')),
            'watch_url' => route('live.show', $this->resource),
        ];
    }
}
