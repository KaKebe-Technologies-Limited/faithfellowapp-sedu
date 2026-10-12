<?php

namespace App\Services;

use App\Models\Church;
use App\Models\PrayerInteraction;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Notifications\Prayer\PrayerAnswered;
use App\Notifications\Prayer\PrayerChainRequest;
use App\Notifications\Prayer\PrayerRequestReviewed;
use App\Notifications\Prayer\PrayerRequestShared;
use App\Notifications\Prayer\PrayerRequestSubmitted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Prayer wall: requests (approved by a pastor before they reach the church),
 * private requests for pastors, "I prayed for you", prayer chains, answers.
 */
class PrayerService
{
    public function __construct(protected NotificationService $notifications) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(User $user, Church $church, array $data): PrayerRequest
    {
        $request = PrayerRequest::create([
            'church_id' => $church->id,
            'user_id' => $user->id,
            'title' => $data['title'] ?? null,
            'body' => strip_tags($data['body']),
            'visibility' => $data['visibility'],
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'chain_enabled' => (bool) ($data['chain_enabled'] ?? false) && $data['visibility'] !== 'pastor',
            // Requests for the whole church wait for a pastor; private ones go straight to the pastors.
            'status' => $data['visibility'] === 'pastor' ? 'open' : 'pending',
        ]);

        // Pastors / prayer team always see new requests.
        Notification::send($this->pastoralTeam($church), new PrayerRequestSubmitted($request->loadMissing('church', 'user')));

        return $request;
    }

    /**
     * A pastor approves a request: it appears on the prayer wall and every
     * church member is invited to stand in prayer.
     */
    public function approve(PrayerRequest $request, User $pastor): void
    {
        if ($request->status !== 'pending') {
            return;
        }

        $request->update(['status' => 'open', 'approved_by' => $pastor->id, 'approved_at' => now()]);
        $request->loadMissing('church', 'user');

        $request->user?->notify(new PrayerRequestReviewed($request, 'approved'));
        $this->notifications->notifyChurch($request->church, new PrayerRequestShared($request), 'members', ['except_user_id' => $request->user_id]);

        if ($request->chain_enabled) {
            $this->advanceChain($request);
        }
    }

    /**
     * Keep a request off the wall; the pastoral team still prays privately.
     */
    public function decline(PrayerRequest $request, User $pastor, ?string $note = null): void
    {
        $request->update(array_filter([
            'status' => 'declined',
            'pastor_response' => $note ?: null,
            'responded_by' => $note ? $pastor->id : null,
            'responded_at' => $note ? now() : null,
        ], fn ($v) => $v !== null));

        $request->user?->notify(new PrayerRequestReviewed($request->loadMissing('church'), 'declined'));
    }

    /**
     * A private reply from the pastor to the person who asked.
     */
    public function respond(PrayerRequest $request, User $pastor, string $response): void
    {
        $request->update(['pastor_response' => trim(strip_tags($response)), 'responded_by' => $pastor->id, 'responded_at' => now()]);
        $request->user?->notify(new PrayerRequestReviewed($request->loadMissing('church'), 'responded'));
    }

    /**
     * Toggle "I prayed for you" (counts unique people).
     *
     * @return array{prayed: bool, count: int}
     */
    public function togglePrayed(PrayerRequest $request, User $user): array
    {
        return DB::transaction(function () use ($request, $user) {
            $existing = PrayerInteraction::where('prayer_request_id', $request->id)->where('user_id', $user->id)->where('type', 'prayed')->first();

            if ($existing) {
                $existing->delete();
                $prayed = false;
            } else {
                PrayerInteraction::create(['church_id' => $request->church_id, 'prayer_request_id' => $request->id, 'user_id' => $user->id, 'type' => 'prayed']);
                $prayed = true;
                // A chain link who prays passes the request on.
                $link = PrayerInteraction::where('prayer_request_id', $request->id)->where('user_id', $user->id)->where('type', 'chain')->whereNull('completed_at')->first();
                if ($link) {
                    $link->update(['completed_at' => now()]);
                    DB::afterCommit(fn () => $this->advanceChain($request->fresh()));
                }
            }

            $count = PrayerInteraction::where('prayer_request_id', $request->id)->where('type', 'prayed')->count();
            $request->forceFill(['prayed_count' => $count])->saveQuietly();

            return ['prayed' => $prayed, 'count' => $count];
        });
    }

    /**
     * Prayer chain: hand the request to the next church member who hasn't
     * been asked yet (excluding the author). Stops after 12 links.
     */
    public function advanceChain(PrayerRequest $request): ?User
    {
        if (! $request->chain_enabled || $request->status !== 'open') {
            return null;
        }

        $asked = PrayerInteraction::where('prayer_request_id', $request->id)->where('type', 'chain')->pluck('user_id');
        if ($asked->count() >= 12 || PrayerInteraction::where('prayer_request_id', $request->id)->where('type', 'chain')->whereNull('completed_at')->exists()) {
            return null;
        }

        $next = User::query()
            ->whereHas('subscriptions', fn ($q) => $q->where('church_id', $request->church_id)->where('status', 'active'))
            ->whereKeyNot($request->user_id)
            ->whereNotIn('id', $asked)
            ->inRandomOrder()
            ->first();

        if (! $next) {
            return null;
        }

        PrayerInteraction::create(['church_id' => $request->church_id, 'prayer_request_id' => $request->id, 'user_id' => $next->id, 'type' => 'chain']);
        $next->notify(new PrayerChainRequest($request->loadMissing('church')));

        return $next;
    }

    public function markAnswered(PrayerRequest $request, ?string $note = null): void
    {
        $request->update(['status' => 'answered', 'answered_at' => now(), 'answer_note' => $note]);

        $prayers = User::whereIn('id', $request->interactions()->where('type', 'prayed')->select('user_id'))->whereKeyNot($request->user_id)->get();
        Notification::send($prayers, new PrayerAnswered($request->loadMissing('church')));
    }

    /**
     * @return Collection<int, User>
     */
    public function pastoralTeam(Church $church)
    {
        $ids = $church->staffMembers()->with('role')->where('status', 'active')->get()
            ->filter(fn ($s) => $s->is_admin || array_intersect(['prayer.moderate', 'care.manage'], $s->role?->permissions ?? []))
            ->pluck('user_id');

        return User::whereIn('id', $ids)->get();
    }
}
