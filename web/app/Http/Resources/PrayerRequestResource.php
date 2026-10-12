<?php

namespace App\Http\Resources;

use App\Models\PrayerRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Anonymous requests never expose the author.
 *
 * @mixin PrayerRequest
 */
class PrayerRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'author' => $this->author_name,
            'is_mine' => $request->user()?->id === $this->user_id,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'prayed_count' => (int) $this->prayed_count,
            'prayed' => $this->when(isset($this->prayed_by_viewer), fn () => (bool) $this->prayed_by_viewer),
            'answer_note' => $this->when($this->status === 'answered', $this->answer_note),
            'created_at' => $this->created_at?->toIso8601String(),
            'church' => new ChurchSummaryResource($this->whenLoaded('church')),
        ];
    }
}
