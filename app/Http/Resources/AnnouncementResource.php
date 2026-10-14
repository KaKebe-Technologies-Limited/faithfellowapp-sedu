<?php

namespace App\Http\Resources;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Announcement */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $text = plain_text($this->body);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $request->routeIs('api.v1.announcements.show') ? $text : Str::limit($text, 160),
            'urgent' => $this->priority === 'urgent',
            'image_url' => $this->image_url,
            'published_at' => $this->published_at?->toIso8601String(),
            'church' => new ChurchSummaryResource($this->whenLoaded('church')),
        ];
    }
}
