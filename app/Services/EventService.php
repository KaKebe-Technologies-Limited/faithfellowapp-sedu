<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\EventTicket;
use App\Models\Sermon;
use App\Models\User;
use App\Notifications\Events\EventReminder;
use App\Notifications\Events\NewEventPublished;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class EventService
{
    public function __construct(protected NotificationService $notifications) {}

    /**
     * RSVP / reserve tickets. Free → confirmed ("going"); paid → pending_payment
     * until the giving flow confirms the payment.
     */
    public function rsvp(User $user, Event $event, int $quantity = 1, ?EventTicket $ticket = null, string $status = 'going'): EventRsvp
    {
        if (! $event->isPublished() || ! $event->rsvp_enabled || $event->isPast()) {
            throw ValidationException::withMessages(['rsvp' => __('RSVPs are closed for this event.')]);
        }

        return DB::transaction(function () use ($user, $event, $quantity, $ticket, $status) {
            // Lock the event row so two people can't take the last seat at once.
            Event::whereKey($event->id)->lockForUpdate()->first();

            $existing = EventRsvp::where('event_id', $event->id)->where('user_id', $user->id)->first();
            $alreadyHeld = $existing && in_array($existing->status, ['going', 'pending_payment'], true) ? $existing->quantity : 0;

            if ($status !== 'maybe' && $event->capacity !== null && $event->seatsTaken() - $alreadyHeld + $quantity > $event->capacity) {
                throw ValidationException::withMessages(['quantity' => __('Sorry, only :n seat(s) left.', ['n' => max(0, $event->capacity - $event->seatsTaken() + $alreadyHeld)])]);
            }

            $amount = 0;
            if ($event->is_paid) {
                if (! $ticket || $ticket->event_id !== $event->id || ! $ticket->isOnSale()) {
                    throw ValidationException::withMessages(['event_ticket_id' => __('Please choose an available ticket.')]);
                }
                if ($ticket->remaining() !== null && $ticket->remaining() < $quantity) {
                    throw ValidationException::withMessages(['quantity' => __('Only :n ticket(s) left for :name.', ['n' => $ticket->remaining(), 'name' => $ticket->name])]);
                }
                $amount = $ticket->price * $quantity;
                $status = $amount > 0 ? 'pending_payment' : 'going';
            }

            return EventRsvp::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $user->id],
                [
                    'church_id' => $event->church_id,
                    'event_ticket_id' => $ticket?->id,
                    'quantity' => $quantity,
                    'status' => $status,
                    'amount' => $amount,
                ],
            );
        });
    }

    /**
     * Called when a ticket payment succeeds (Sprint 6 payment webhooks).
     */
    public function confirmPaidRsvp(EventRsvp $rsvp, ?int $donationId = null): void
    {
        DB::transaction(function () use ($rsvp, $donationId) {
            if ($rsvp->status === 'going') {
                return;
            }
            $rsvp->update(['status' => 'going', 'donation_id' => $donationId]);
            $rsvp->ticket?->increment('sold_count', $rsvp->quantity);
        });
    }

    public function cancel(EventRsvp $rsvp): void
    {
        DB::transaction(function () use ($rsvp) {
            if ($rsvp->status === 'going' && $rsvp->ticket && $rsvp->amount > 0) {
                $rsvp->ticket->decrement('sold_count', $rsvp->quantity);
            }
            $rsvp->update(['status' => 'cancelled']);
        });
    }

    /**
     * QR / code check-in at the door.
     *
     * @return array{ok: bool, message: string, rsvp: ?EventRsvp}
     */
    public function checkIn(Event $event, string $code, User $staff): array
    {
        $rsvp = EventRsvp::with('user')->where('event_id', $event->id)->where('code', strtoupper(trim($code)))->first();

        if (! $rsvp) {
            return ['ok' => false, 'message' => __('Ticket not found for this event.'), 'rsvp' => null];
        }
        if ($rsvp->status !== 'going') {
            return ['ok' => false, 'message' => __('This ticket is :status.', ['status' => str_replace('_', ' ', $rsvp->status)]), 'rsvp' => $rsvp];
        }
        if ($rsvp->checked_in_at) {
            return ['ok' => false, 'message' => __('Already checked in at :time.', ['time' => $rsvp->checked_in_at->format('g:i A')]), 'rsvp' => $rsvp];
        }

        $rsvp->update(['checked_in_at' => now(), 'checked_in_by' => $staff->id]);

        return ['ok' => true, 'message' => __('Welcome, :name! (:n guest(s))', ['name' => $rsvp->user?->name, 'n' => $rsvp->quantity]), 'rsvp' => $rsvp];
    }

    public function announce(Event $event): void
    {
        if ($event->notified_at || ! $event->isPublished() || $event->isPast()) {
            return;
        }
        $event->forceFill(['notified_at' => now()])->saveQuietly();
        $event->loadMissing('church');
        $this->notifications->notifyChurch($event->church, new NewEventPublished($event));
    }

    /**
     * Send 24h and 1h reminders to people going (scheduled every 5 minutes).
     */
    public function sendReminders(): int
    {
        $sent = 0;
        $windows = [
            '24h' => ['column' => 'reminder_24h_sent_at', 'from' => now()->addHours(23), 'to' => now()->addHours(24)],
            '1h' => ['column' => 'reminder_1h_sent_at', 'from' => now(), 'to' => now()->addHour()],
        ];

        foreach ($windows as $window => $w) {
            Event::with('church')->published()->whereNull($w['column'])
                ->whereBetween('starts_at', [$w['from'], $w['to']])
                ->each(function (Event $event) use ($window, $w, &$sent) {
                    $event->forceFill([$w['column'] => now()])->saveQuietly();
                    $users = User::whereIn('id', $event->rsvps()->where('status', 'going')->select('user_id'))->get();
                    Notification::send($users, new EventReminder($event, $window));
                    $sent += $users->count();
                });
        }

        return $sent;
    }

    /**
     * Event recording → auto-upload as a sermon.
     */
    public function publishRecording(Event $event): Sermon
    {
        abort_unless($event->recording_url, 422, __('Add the recording link first.'));

        $sermon = Sermon::create([
            'church_id' => $event->church_id,
            'branch_id' => $event->branch_id,
            'user_id' => auth()->id(),
            'title' => $event->title,
            'description' => $event->description,
            'video_url' => $event->recording_url,
            'language' => 'en',
            'status' => 'published',
            'visibility' => $event->visibility,
            'published_at' => now(),
            'preached_on' => $event->starts_at->toDateString(),
        ]);
        $event->update(['recording_sermon_id' => $sermon->id]);
        Audit::log('event.recording_published', $event, $event->title);

        return $sermon;
    }
}
