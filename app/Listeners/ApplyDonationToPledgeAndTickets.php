<?php

namespace App\Listeners;

use App\Events\DonationSucceeded;
use App\Services\EventService;
use Illuminate\Support\Facades\DB;

/**
 * Synchronous (not queued): pledge balances, recurring gift bookkeeping and
 * ticket confirmation must be correct the moment the giver sees "Thank you".
 */
class ApplyDonationToPledgeAndTickets
{
    public function __construct(protected EventService $events) {}

    public function handle(DonationSucceeded $event): void
    {
        $donation = $event->donation;

        if ($donation->pledge_id) {
            DB::transaction(function () use ($donation) {
                $pledge = $donation->pledge()->lockForUpdate()->first();
                if ($pledge) {
                    $pledge->paid_amount = (float) $pledge->paid_amount + (float) $donation->amount;
                    if ($pledge->paid_amount >= (float) $pledge->amount) {
                        $pledge->status = 'fulfilled';
                    }
                    $pledge->save();
                }
            });
        }

        if ($donation->recurring_gift_id) {
            $donation->recurringGift()->update(['failures' => 0]);
        }

        if ($donation->event_rsvp_id && $rsvp = $donation->rsvp()->first()) {
            $this->events->confirmPaidRsvp($rsvp, $donation->id);
        }
    }
}
