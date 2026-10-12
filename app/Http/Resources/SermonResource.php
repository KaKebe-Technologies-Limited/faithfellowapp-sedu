<?php

namespace App\Http\Resources;

use App\Models\Sermon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sermon */
class SermonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'preacher' => $this->preacher,
            'scripture' => $this->scripture,
            'description' => $this->when($request->routeIs('api.v1.sermons.show'), $this->description),
            'series' => $this->whenLoaded('series', fn () => $this->series?->title),
            'language' => $this->language,
            'duration_seconds' => $this->duration_seconds,
            'audio_url' => $this->audio_src,
            'video_url' => $this->video_embed_url ?? $this->video_file_src,
            'thumbnail_url' => $this->thumbnail_url,
            'visibility' => $this->visibility,
            'published_at' => $this->published_at?->toIso8601String(),
            'church' => new ChurchSummaryResource($this->whenLoaded('church')),
            'web_url' => route('sermons.show', $this->resource),
        ];
    }
}
