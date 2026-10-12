<?php

namespace App\Listeners;

use App\Events\DonationSucceeded;
use App\Notifications\Giving\DonationReceiptNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Instant receipt: PDF by email + in-app (+ SMS when the giver opted in).
 */
class SendDonationReceipt implements ShouldQueue
{
    public function handle(DonationSucceeded $event): void
    {
        $donation = $event->donation->fresh(['church', 'user', 'campaign']);

        if (! $donation || $donation->receipt_sent_at || ! $donation->user) {
            return;
        }

        $donation->user->notify(new DonationReceiptNotification($donation));
        $donation->forceFill(['receipt_sent_at' => now()])->saveQuietly();
    }
}
