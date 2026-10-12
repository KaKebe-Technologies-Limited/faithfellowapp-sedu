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
            'ticket' => $this->whenLoaded('ticket', fn () => $this->ticket ? ['id' => $this->ticket->id, 'name' => $this->ticket->name, 'price' => (float) $this->ticket->price] : null),
            'checked_in' => $this->checked_in_at !== null,
            'event' => new EventResource($this->whenLoaded('event')),
        ];
    }
}
