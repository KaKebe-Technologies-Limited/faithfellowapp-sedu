<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventQuestion;
use App\Models\EventRsvp;
use App\Models\EventTicket;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EventController extends Controller
{
    public function __construct(protected EventService $events) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $churchIds = $user->memberChurchIds();

        $events = Event::with(['church.media', 'media'])
            ->forChurches($churchIds)->published()->upcoming()
            ->orderBy('starts_at')
            ->paginate(12)->withQueryString();

        $myRsvps = EventRsvp::where('user_id', $user->id)->whereIn('event_id', $events->pluck('id'))->pluck('status', 'event_id');

        $myTickets = EventRsvp::with(['event.church.media'])->where('user_id', $user->id)->where('status', 'going')
            ->whereHas('event', fn ($q) => $q->upcoming())->get();

        return view('member.events.index', compact('events', 'myRsvps', 'myTickets'));
    }

    public function show(Request $request, Event $event): View
    {
        $this->authorize('view', $event);
        $event->load(['church.media', 'media', 'tickets', 'recordingSermon', 'questions' => fn ($q) => $q->with('user')->where('is_hidden', false)->orderByDesc('upvotes')->latest()]);

        $rsvp = $request->user()
            ? EventRsvp::where('event_id', $event->id)->where('user_id', $request->user()->id)->first()
            : null;

        return view('member.events.show', [
            'event' => $event,
            'rsvp' => $rsvp,
            'seatsLeft' => $event->seatsLeft(),
            'goingCount' => (int) $event->rsvps()->where('status', 'going')->sum('quantity'),
            'canPay' => Route::has('give.ticket'),
        ]);
    }

    public function rsvp(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('view', $event);
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'status' => ['nullable', 'in:going,maybe'],
            'event_ticket_id' => ['nullable', 'integer'],
        ]);

        $ticket = isset($data['event_ticket_id']) ? EventTicket::where('event_id', $event->id)->find($data['event_ticket_id']) : null;
        $rsvp = $this->events->rsvp($request->user(), $event, (int) ($data['quantity'] ?? 1), $ticket, $data['status'] ?? 'going');

        if ($rsvp->status === 'pending_payment' && Route::has('give.ticket')) {
            return redirect()->route('give.ticket', $rsvp);
        }

        return redirect()->route('events.show', $event)->with('success', match ($rsvp->status) {
            'maybe' => __('Marked as maybe.'),
            'pending_payment' => __('Seats reserved — complete payment to confirm.'),
            default => __('You’re going! Your ticket QR code is below.'),
        });
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $rsvp = EventRsvp::where('event_id', $event->id)->where('user_id', $request->user()->id)->firstOrFail();
        $this->events->cancel($rsvp);

        return back()->with('success', __('Your RSVP was cancelled.'));
    }

    /**
     * Printable / offline-friendly ticket with QR code.
     */
    public function ticket(Request $request, Event $event): View
    {
        $rsvp = EventRsvp::with('ticket')->where('event_id', $event->id)->where('user_id', $request->user()->id)->where('status', 'going')->firstOrFail();
        $event->load('church.media');

        return view('member.events.ticket', [
            'event' => $event,
            'rsvp' => $rsvp,
            'qr' => QrCode::format('svg')->size(260)->margin(1)->errorCorrection('M')
                ->generate(route('church-admin.events.check-in.code', [$event->church, $event, $rsvp->code])),
        ]);
    }

    public function ask(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('view', $event);
        abort_unless($event->qa_enabled, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);

        $event->questions()->create(['church_id' => $event->church_id, 'user_id' => $request->user()->id, 'body' => $data['body']]);

        return back()->with('success', __('Question sent to the speakers.'));
    }

    public function upvote(Request $request, Event $event, EventQuestion $question): RedirectResponse
    {
        abort_unless($question->event_id === $event->id, 404);
        $key = 'upvoted.q.'.$question->id;
        if (! $request->session()->has($key)) {
            $request->session()->put($key, true);
            $question->increment('upvotes');
        }

        return back();
    }
}
