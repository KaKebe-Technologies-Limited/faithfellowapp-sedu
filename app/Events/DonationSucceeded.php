<?php

namespace App\Events;

use App\Models\Donation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired exactly once when a gift is confirmed. Listeners (all queued):
 * receipt, campaign progress, finance alert, pledge balance, event ticket.
 * Also broadcast to the church finance channel for real-time dashboards.
 */
class DonationSucceeded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Donation $donation) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('church.'.$this->donation->church_id.'.finance')];
    }

    public function broadcastAs(): string
    {
        return 'donation.succeeded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'amount' => (float) $this->donation->amount,
            'category' => $this->donation->category,
            'campaign_id' => $this->donation->campaign_id,
            'at' => $this->donation->paid_at?->toIso8601String(),
        ];
    }
}
