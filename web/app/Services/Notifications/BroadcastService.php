<?php

namespace App\Services\Notifications;

use App\Models\ChurchBroadcast;
use App\Notifications\Church\ChurchMessage;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sends messages from the church notification composer (now or scheduled).
 */
class BroadcastService
{
    public function __construct(protected NotificationService $notifications) {}

    public function send(ChurchBroadcast $broadcast): ChurchBroadcast
    {
        // Claim the row so a scheduler overlap can never send twice.
        $claimed = ChurchBroadcast::whereKey($broadcast->id)->whereIn('status', ['draft', 'scheduled'])->update(['status' => 'sending']);
        if (! $claimed) {
            return $broadcast->fresh();
        }

        $broadcast->refresh()->load('church');
        $options = $broadcast->audience_options ?? [];

        $broadcast->update([
            'recipients_count' => $this->notifications->recipients($broadcast->church_id, $broadcast->audience, $options)->count(),
        ]);

        $this->notifications->notifyChurch($broadcast->church, ChurchMessage::fromBroadcast($broadcast), $broadcast->audience, $options);

        $broadcast->update(['status' => 'sent', 'sent_at' => now()]);

        return $broadcast;
    }

    /**
     * Scheduled messages whose time has come (every minute).
     */
    public function sendDue(): int
    {
        $due = ChurchBroadcast::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get();
        $due->each(fn ($b) => $this->send($b));

        return $due->count();
    }

    /**
     * Audience size and how many have a phone number (for the SMS cost estimate).
     *
     * @param  array<string, mixed>  $options
     * @return array{recipients: int, with_phone: int, sms_cost: float}
     */
    public function estimate(int $churchId, string $audience, array $options, int $segments = 1): array
    {
        $query = $this->notifications->recipients($churchId, $audience, $options);
        $withPhone = (clone $query)->whereNotNull('phone')->where('phone', '!=', '')->count();

        return [
            'recipients' => $query->count(),
            'with_phone' => $withPhone,
            'sms_cost' => round($withPhone * $segments * (float) config('services.africastalking.sms_cost', 35)),
        ];
    }

    /**
     * Delivery stats per channel for one broadcast.
     *
     * @return Collection<string, object>
     */
    public function stats(ChurchBroadcast $broadcast)
    {
        return DB::table('notification_deliveries')->where('church_broadcast_id', $broadcast->id)
            ->selectRaw("channel, SUM(status = 'sent') sent, SUM(status = 'failed') failed, SUM(cost) cost")
            ->groupBy('channel')->get()->keyBy('channel');
    }
}
