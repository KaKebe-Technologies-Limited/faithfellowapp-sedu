<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\EventRsvp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $request->routeIs('api.v1.events.show');
        $user = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->when($detail, fn () => strip_tags((string) $this->description)),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'location' => ['name' => $this->location_name, 'address' => $this->address, 'lat' => $this->lat ? (float) $this->lat : null, 'lng' => $this->lng ? (float) $this->lng : null],
            'is_online' => (bool) $this->is_online,
            'is_paid' => (bool) $this->is_paid,
            'rsvp_enabled' => (bool) $this->rsvp_enabled,
            'capacity' => $this->capacity,
            'cover_url' => $this->picture_url,
            'church' => new ChurchSummaryResource($this->whenLoaded('church')),
            'web_url' => route('events.show', $this->resource),

            // Detail-only: everything the event screen needs to reserve a seat without leaving the app.
            'is_past' => $this->when($detail, fn () => $this->isPast()),
            'agenda' => $this->when($detail, fn () => $this->agenda ?? []),
            'speakers' => $this->when($detail, fn () => $this->speakers ?? []),
            'online_url' => $this->when($detail && $this->is_online, fn () => $this->online_url),
            'qa_enabled' => $this->when($detail, fn () => (bool) $this->qa_enabled),
            'seats_left' => $this->when($detail, fn () => $this->seatsLeft()),
            'going_count' => $this->when($detail, fn () => (int) $this->rsvps()->where('status', 'going')->sum('quantity')),
            'tickets' => $this->when($detail, fn () => $this->tickets->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name, 'price' => (float) $t->price, 'currency' => $t->currency,
                'remaining' => $t->remaining(), 'on_sale' => $t->isOnSale(),
            ])->values()),
            'my_rsvp' => $this->when($detail && $user, fn () => ($rsvp = EventRsvp::with('ticket')->where('event_id', $this->id)->where('user_id', $user->id)->first())
                ? new EventRsvpResource($rsvp->setRelation('event', $this->resource)) : null),
            'questions' => $this->when($detail && $this->qa_enabled, fn () => $this->questions()->with('user:id,name')->where('is_hidden', false)
                ->orderByDesc('upvotes')->latest()->take(20)->get()->map(fn ($q) => [
                    'id' => $q->id, 'body' => $q->body, 'author' => $q->user?->name, 'is_answered' => (bool) $q->is_answered, 'mine' => $q->user_id === $user?->id,
                ])->values()),
        ];
    }
}
