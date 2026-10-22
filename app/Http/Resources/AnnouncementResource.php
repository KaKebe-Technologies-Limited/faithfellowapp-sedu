<?php

namespace App\Http\Resources;

use App\Models\Announcement;
use App\Models\Reaction;
use App\Services\Reactions;
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
            // The reactions on the full announcement: which are offered, how many of each, and which one is this person's.
            'reactions' => $this->when($request->routeIs('api.v1.announcements.show'), fn () => [
                'types' => collect(Reaction::available())->only(['like', 'love', 'smile'])->merge(collect(Reaction::available())->only(['amen', 'pray']))->take(3)
                    ->map(fn ($emoji, $key) => ['key' => $key, 'emoji' => $emoji])->values()->all(),
                'counts' => (object) app(Reactions::class)->counts($this->resource),
                'mine' => app(Reactions::class)->mine($this->resource, $request->user()),
            ]),
        ];
    }
}
