<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->when($request->routeIs('api.v1.events.show'), fn () => strip_tags((string) $this->description)),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'location' => ['name' => $this->location_name, 'address' => $this->address, 'lat' => $this->lat ? (float) $this->lat : null, 'lng' => $this->lng ? (float) $this->lng : null],
            'is_online' => (bool) $this->is_online,
            'is_paid' => (bool) $this->is_paid,
            'rsvp_enabled' => (bool) $this->rsvp_enabled,
            'capacity' => $this->capacity,
            'cover_url' => $this->cover_url,
            'church' => new ChurchSummaryResource($this->whenLoaded('church')),
            'web_url' => route('events.show', $this->resource),
        ];
    }
}
