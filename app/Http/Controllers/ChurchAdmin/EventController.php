<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\EventRequest;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventQuestion;
use App\Models\EventRsvp;
use App\Services\EventService;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    public function __construct(protected EventService $events) {}

    public function index(Request $request, Church $church): View
    {
        $when = $request->query('when', 'upcoming');

        $events = $church->events()->with(['media', 'branch'])
            ->withCount(['rsvps as going_count' => fn ($q) => $q->where('status', 'going')])
            ->withSum(['rsvps as guests_sum' => fn ($q) => $q->where('status', 'going')], 'quantity')
            ->when($when === 'upcoming', fn ($q) => $q->upcoming()->orderBy('starts_at'))
            ->when($when === 'past', fn ($q) => $q->where('starts_at', '<', now())->orderByDesc('starts_at'))
            ->paginate(20)->withQueryString();

        return view('church-admin.events.index', compact('church', 'events', 'when'));
    }

    public function create(Church $church): View
    {
        return $this->form($church, new Event([
            'starts_at' => now()->addWeek()->setTime(10, 0), 'status' => 'published', 'visibility' => 'public', 'rsvp_enabled' => true,
        ]));
    }

    public function store(EventRequest $request, Church $church): RedirectResponse
    {
        $event = DB::transaction(function () use ($request, $church) {
            $event = $church->events()->create([...$this->attributes($request), 'user_id' => $request->user()->id]);
            $this->syncTickets($event, $request->input('tickets', []));

            return $event;
        });
        if ($request->hasFile('cover')) {
            $event->addMediaFromRequest('cover')->toMediaCollection('cover');
        }
        $this->events->announce($event->load('church'));

        return redirect()->route('church-admin.events.show', [$church, $event])->with('success', __('Event saved.'));
    }

    public function show(Church $church, Event $event): View
    {
        $event->load(['tickets', 'media', 'questions' => fn ($q) => $q->with('user')->orderByDesc('upvotes')]);

        $rsvps = $event->rsvps()->with(['user.media', 'ticket'])->where('status', '!=', 'cancelled')->latest()->paginate(30);
        $stats = [
            'going' => (int) $event->rsvps()->where('status', 'going')->sum('quantity'),
            'pending' => $event->rsvps()->where('status', 'pending_payment')->count(),
            'checked_in' => (int) $event->rsvps()->whereNotNull('checked_in_at')->sum('quantity'),
            'revenue' => (float) $event->rsvps()->where('status', 'going')->sum('amount'),
        ];

        return view('church-admin.events.show', compact('church', 'event', 'rsvps', 'stats'));
    }

    public function edit(Church $church, Event $event): View
    {
        return $this->form($church, $event->load(['tickets', 'media']));
    }

    public function update(EventRequest $request, Church $church, Event $event): RedirectResponse
    {
        DB::transaction(function () use ($request, $event) {
            $event->update($this->attributes($request));
            $this->syncTickets($event, $request->input('tickets', []));
        });
        if ($request->hasFile('cover')) {
            $event->addMediaFromRequest('cover')->toMediaCollection('cover');
        }
        $this->events->announce($event->load('church'));

        return redirect()->route('church-admin.events.show', [$church, $event])->with('success', __('Event updated.'));
    }

    public function destroy(Church $church, Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('church-admin.events.index', $church)->with('success', __('Event deleted.'));
    }

    public function export(Church $church, Event $event): StreamedResponse
    {
        $rows = $event->rsvps()->with(['user', 'ticket'])->where('status', '!=', 'cancelled')->cursor()
            ->map(fn (EventRsvp $r) => [$r->user?->name, $r->user?->phone, $r->ticket?->name, $r->quantity, $r->status, $r->amount, $r->code, $r->checked_in_at?->format('Y-m-d H:i')]);

        return CsvExport::download('attendees-'.$event->slug.'.csv', ['Name', 'Phone', 'Ticket', 'Guests', 'Status', 'Amount', 'Code', 'Checked in'], $rows);
    }

    /**
     * Door check-in screen (manual code entry; scanning a ticket QR opens checkInCode).
     */
    public function checkInScreen(Church $church, Event $event): View
    {
        return view('church-admin.events.check-in', [
            'church' => $church,
            'event' => $event,
            'result' => session('checkin'),
            'checkedIn' => (int) $event->rsvps()->whereNotNull('checked_in_at')->sum('quantity'),
            'expected' => (int) $event->rsvps()->where('status', 'going')->sum('quantity'),
        ]);
    }

    public function checkIn(Request $request, Church $church, Event $event): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $result = $this->events->checkIn($event, $request->input('code'), $request->user());

        return redirect()->route('church-admin.events.check-in', [$church, $event])
            ->with('checkin', ['ok' => $result['ok'], 'message' => $result['message']]);
    }

    /**
     * Staff scan a member's ticket QR with any phone camera → this URL.
     */
    public function checkInCode(Request $request, Church $church, Event $event, string $code): RedirectResponse
    {
        $result = $this->events->checkIn($event, $code, $request->user());

        return redirect()->route('church-admin.events.check-in', [$church, $event])
            ->with('checkin', ['ok' => $result['ok'], 'message' => $result['message']]);
    }

    public function publishRecording(Church $church, Event $event): RedirectResponse
    {
        $sermon = $this->events->publishRecording($event);

        return redirect()->route('church-admin.sermons.edit', [$church, $sermon])->with('success', __('Recording published as a sermon. Add the preacher and scripture.'));
    }

    public function moderateQuestion(Request $request, Church $church, Event $event, EventQuestion $question): RedirectResponse
    {
        $question->update($request->validate(['is_answered' => ['sometimes', 'boolean'], 'is_hidden' => ['sometimes', 'boolean']]));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(EventRequest $request): array
    {
        $data = $request->safe()->except(['cover', 'tickets']);
        $data['description'] = isset($data['description']) ? ff_safe_html($data['description']) : null;
        $data['agenda'] = collect($data['agenda'] ?? [])->filter(fn ($a) => ! empty($a['title']))->values()->all();
        $data['speakers'] = collect($data['speakers'] ?? [])->filter(fn ($s) => ! empty($s['name']))->values()->all();

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tickets
     */
    protected function syncTickets(Event $event, array $tickets): void
    {
        $keep = [];
        foreach ($tickets as $t) {
            if (empty($t['name'])) {
                continue;
            }
            $ticket = $event->tickets()->updateOrCreate(
                ['id' => $t['id'] ?? null],
                ['church_id' => $event->church_id, 'name' => $t['name'], 'price' => $t['price'] ?? 0, 'quantity' => $t['quantity'] ?? null],
            );
            $keep[] = $ticket->id;
        }
        $event->tickets()->whereNotIn('id', $keep)->where('sold_count', 0)->delete();
    }

    protected function form(Church $church, Event $event): View
    {
        return view('church-admin.events.form', [
            'church' => $church,
            'event' => $event,
            'branches' => $church->branches()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
