<?php

namespace App\Http\Resources;

use App\Models\EventRsvp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member's reservation for an event: the status, the seats and the check-in code that makes the ticket QR.
 *
 * @mixin EventRsvp
 */
class EventRsvpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'quantity' => (int) $this->quantity,
            'amount' => (float) $this->amount,
            'code' => $this->status === 'going' ? $this->code : null,
            // What the door scanner reads from the ticket's QR picture.
            'qr' => $this->status === 'going' ? $this->checkInUrl() : null,
            'ticket' => $this->whenLoaded('ticket', fn () => $this->ticket ? ['id' => $this->ticket->id, 'name' => $this->ticket->name, 'price' => (float) $this->ticket->price] : null),
            'checked_in' => $this->checked_in_at !== null,
            // Not repeated inside the event's own detail (that would nest the event in itself).
            'event' => $this->when(! $request->routeIs('api.v1.events.show'), fn () => new EventResource($this->event)),
        ];
    }
}
