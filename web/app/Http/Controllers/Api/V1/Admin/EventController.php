<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\EventRequest;
use App\Models\Church;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Running events from the phone: create and edit them, see who is coming, and check people in at the door
 * (typing the ticket code or scanning its QR code).
 */
class EventController extends Controller
{
    public function __construct(protected EventService $events) {}

    public function index(Church $church): JsonResponse
    {
        return response()->json(['data' => $church->events()->with('media')->orderByDesc('starts_at')->take(40)->get()->map(fn (Event $e) => $this->card($e))->values()]);
    }

    public function show(Church $church, Event $event): JsonResponse
    {
        $event->load(['tickets', 'media']);
        $rsvps = $event->rsvps()->with('user.media')->where('status', '!=', 'cancelled')->latest()->take(200)->get();

        return response()->json(['data' => $this->card($event) + [
            'description' => plain_text($event->description),
            'tickets' => $event->tickets->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'price' => (float) $t->price, 'quantity' => $t->quantity, 'sold' => (int) $t->sold_count])->values(),
            'stats' => [
                'going' => (int) $event->rsvps()->where('status', 'going')->sum('quantity'),
                'pending' => $event->rsvps()->where('status', 'pending_payment')->count(),
                'checked_in' => (int) $event->rsvps()->whereNotNull('checked_in_at')->sum('quantity'),
                'revenue' => (float) $event->rsvps()->where('status', 'going')->sum('amount'),
            ],
            'attendees' => $rsvps->map(fn ($r) => ['id' => $r->id, 'name' => $r->user?->name, 'avatar_url' => $r->user?->avatar_url, 'quantity' => (int) $r->quantity, 'status' => $r->status, 'code' => $r->code, 'checked_in' => $r->checked_in_at !== null])->values(),
        ]]);
    }

    public function store(EventRequest $request, Church $church): JsonResponse
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

        return response()->json(['data' => $this->card($event->fresh('media'))], 201);
    }

    public function update(EventRequest $request, Church $church, Event $event): JsonResponse
    {
        DB::transaction(function () use ($request, $event) {
            $event->update($this->attributes($request));
            $this->syncTickets($event, $request->input('tickets', []));
        });
        if ($request->hasFile('cover')) {
            $event->addMediaFromRequest('cover')->toMediaCollection('cover');
        }
        $this->events->announce($event->load('church'));

        return response()->json(['data' => $this->card($event->fresh('media'))]);
    }

    public function destroy(Church $church, Event $event): JsonResponse
    {
        $event->delete();

        return response()->json(['deleted' => true]);
    }

    /** Door check-in with the ticket code (the QR code carries the same code in its address). */
    public function checkIn(Request $request, Church $church, Event $event): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:300']]);
        // A scanned QR code is the check-in address; the code is its last part.
        $code = str_contains($data['code'], '/') ? basename(parse_url($data['code'], PHP_URL_PATH) ?: $data['code']) : $data['code'];
        $result = $this->events->checkIn($event, $code, $request->user());

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'attendee' => $result['rsvp'] ? ['name' => $result['rsvp']->user?->name, 'quantity' => (int) $result['rsvp']->quantity] : null,
            'checked_in' => (int) $event->rsvps()->whereNotNull('checked_in_at')->sum('quantity'),
            'expected' => (int) $event->rsvps()->where('status', 'going')->sum('quantity'),
        ], $result['ok'] ? 200 : 422);
    }

    /** @return array<string, mixed> */
    protected function card(Event $e): array
    {
        return [
            'id' => $e->id, 'title' => $e->title, 'starts_at' => $e->starts_at?->toIso8601String(), 'ends_at' => $e->ends_at?->toIso8601String(),
            'location' => $e->location_name, 'address' => $e->address, 'status' => $e->status, 'visibility' => $e->visibility, 'is_paid' => (bool) $e->is_paid,
            'is_online' => (bool) $e->is_online, 'capacity' => $e->capacity, 'rsvp_enabled' => (bool) $e->rsvp_enabled, 'qa_enabled' => (bool) $e->qa_enabled,
            'cover_url' => $e->picture_url, 'going' => (int) $e->rsvps()->where('status', 'going')->sum('quantity'),
        ];
    }

    /** @return array<string, mixed> */
    protected function attributes(EventRequest $request): array
    {
        $data = $request->safe()->except(['cover', 'tickets']);
        $data['description'] = isset($data['description']) ? ff_safe_html(nl2br(e($data['description']))) : null;
        $data['agenda'] = collect($data['agenda'] ?? [])->filter(fn ($a) => ! empty($a['title']))->values()->all();
        $data['speakers'] = collect($data['speakers'] ?? [])->filter(fn ($s) => ! empty($s['name']))->values()->all();

        return $data;
    }

    /** @param  array<int, array<string, mixed>>  $tickets */
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
}
