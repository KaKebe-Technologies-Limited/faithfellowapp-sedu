<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithPayment;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventRsvpResource;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\EventTicket;
use App\Models\GivingChannel;
use App\Services\DonationService;
use App\Services\EventService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Reserving a seat, paying for a ticket, asking a question: everything a member does with an event,
 * so the app never has to send them to the website.
 */
class EventController extends Controller
{
    use RespondsWithPayment;

    public function __construct(protected EventService $events, protected DonationService $donations) {}

    /** The member's upcoming tickets (going, or reserved and waiting for payment). */
    public function tickets(Request $request): AnonymousResourceCollection
    {
        return EventRsvpResource::collection(EventRsvp::with(['event.church.media', 'event.media', 'ticket'])
            ->where('user_id', $request->user()->id)->whereIn('status', ['going', 'pending_payment'])
            ->whereHas('event', fn ($q) => $q->upcoming())->get()->sortBy('event.starts_at')->values());
    }

    public function rsvp(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'status' => ['nullable', Rule::in(['going', 'maybe'])],
            'event_ticket_id' => ['nullable', 'integer'],
        ]);

        $ticket = isset($data['event_ticket_id']) ? EventTicket::where('event_id', $event->id)->find($data['event_ticket_id']) : null;
        $rsvp = $this->events->rsvp($request->user(), $event, (int) ($data['quantity'] ?? 1), $ticket, $data['status'] ?? 'going');

        return (new EventRsvpResource($rsvp->load(['ticket', 'event.church.media', 'event.media'])))
            ->additional(['next' => ['action' => $rsvp->status === 'pending_payment' ? 'pay' : 'done']])
            ->response()->setStatusCode(201);
    }

    public function cancel(Request $request, Event $event): JsonResponse
    {
        $rsvp = EventRsvp::where('event_id', $event->id)->where('user_id', $request->user()->id)->firstOrFail();
        $this->events->cancel($rsvp);

        return response()->json(['status' => 'cancelled']);
    }

    /** Pay for a reserved ticket: the same mobile money / card flow as giving, with the amount fixed by the ticket. */
    public function pay(Request $request, EventRsvp $rsvp): JsonResponse
    {
        abort_unless($rsvp->user_id === $request->user()->id, 403);
        abort_unless($rsvp->status === 'pending_payment', 422, __('This ticket does not need payment.'));
        $rsvp->load('event.church.givingChannels');

        $data = $request->validate([
            'method' => ['required', Rule::in(GivingChannel::ONLINE), function ($attribute, $value, $fail) {
                if (! PaymentService::enabled((string) $value)) {
                    $fail(__('This payment method is temporarily unavailable. Please choose another.'));
                }
            }],
            'phone' => ['nullable', 'required_if:method,iotec,mtn_momo,airtel_money', 'regex:/^(\+?256|0)?7\d{8}$/'],
        ]);

        ['donation' => $donation, 'result' => $result] = $this->donations->initiate($request->user(), $rsvp->event->church, [
            ...$data, 'amount' => $rsvp->amount, 'category' => 'event', 'event_rsvp_id' => $rsvp->id,
            'country' => strtoupper($request->user()->country ?? 'UG'), 'return_to' => 'app',
        ]);

        return $this->paymentStarted($donation, $result);
    }

    public function ask(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);
        abort_unless($event->qa_enabled, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);

        $question = $event->questions()->create(['church_id' => $event->church_id, 'user_id' => $request->user()->id, 'body' => $data['body']]);

        return response()->json(['data' => ['id' => $question->id, 'body' => $question->body, 'author' => $request->user()->name, 'is_answered' => false, 'mine' => true]], 201);
    }
}
