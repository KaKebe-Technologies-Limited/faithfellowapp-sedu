<?php

namespace App\Http\Resources;

use App\Models\Devotional;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Devotional */
class DevotionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $request->routeIs('api.v1.devotionals.show');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'scripture' => $this->scripture,
            'scripture_text' => $this->scripture_text,
            'excerpt' => Str::limit(trim(strip_tags((string) $this->body)), 180),
            'body' => $this->when($detail, fn () => plain_text($this->body)),
            'prayer' => $this->when($detail, fn () => $this->prayer ? plain_text($this->prayer) : null),
            'audio_url' => $this->audio_src,
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'publish_date' => $this->publish_date?->toDateString(),
            'church' => new ChurchSummaryResource($this->whenLoaded('church')),
        ];
    }
}
