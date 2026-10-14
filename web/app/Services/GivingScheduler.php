<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\Pledge;
use App\Models\RecurringGift;
use App\Notifications\Giving\GivingReminder;
use Throwable;

/**
 * Scheduled giving jobs: recurring gifts, pledge reminders, stale payment cleanup.
 */
class GivingScheduler
{
    public function __construct(protected DonationService $donations) {}

    /**
     * Charge recurring gifts that are due today. Mobile money sends a prompt
     * to the giver's phone; card givers get a reminder with a pay link
     * (off-session card charges need a saved Stripe payment method).
     */
    public function processRecurring(): int
    {
        $count = 0;

        RecurringGift::with(['church', 'user'])->where('status', 'active')->whereDate('next_run_on', '<=', today())
            ->each(function (RecurringGift $gift) use (&$count) {
                $gift->update(['next_run_on' => $gift->nextDateAfter(today()), 'last_run_at' => now()]);

                if (! $gift->user || ! $gift->church?->isActive()) {
                    return;
                }

                if ($gift->method === 'stripe') {
                    $gift->user->notify(new GivingReminder($gift->church, __('Your :freq gift of :amount is due', ['freq' => $gift->frequency, 'amount' => ff_money($gift->amount)]), route('give.create', $gift->church)));

                    return;
                }

                try {
                    $this->donations->initiate($gift->user, $gift->church, [
                        'amount' => $gift->amount, 'category' => $gift->category, 'method' => $gift->method,
                        'phone' => $gift->phone, 'campaign_id' => $gift->campaign_id, 'recurring_gift_id' => $gift->id, 'reason_id' => $gift->giving_reason_id,
                    ]);
                    $count++;
                } catch (Throwable $e) {
                    $gift->increment('failures');
                    if ($gift->failures >= 3) {
                        $gift->update(['status' => 'paused']);
                    }
                    report($e);
                }
            });

        return $count;
    }

    /**
     * Weekly pledge reminders + mark overdue pledges.
     */
    public function remindPledges(): int
    {
        Pledge::where('status', 'active')->whereNotNull('due_date')->whereDate('due_date', '<', today())->update(['status' => 'overdue']);

        $count = 0;
        Pledge::with(['church', 'user', 'campaign'])->whereIn('status', ['active', 'overdue'])->whereDate('next_reminder_on', '<=', today())
            ->each(function (Pledge $pledge) use (&$count) {
                $interval = match ($pledge->frequency) {
                    'weekly' => 7, 'quarterly' => 90, 'once' => 14, default => 30,
                };
                $pledge->update(['next_reminder_on' => today()->addDays($interval)]);
                if (! $pledge->user || ! $pledge->church) {
                    return;
                }
                $pledge->user->notify(new GivingReminder(
                    $pledge->church,
                    __('Pledge reminder: :paid of :total paid', ['paid' => ff_money($pledge->paid_amount), 'total' => ff_money($pledge->amount)])
                        .($pledge->campaign ? ' — '.$pledge->campaign->title : ''),
                    route('give.create', [$pledge->church, 'pledge' => $pledge->id, 'campaign' => $pledge->campaign?->slug]),
                ));
                $count++;
            });

        return $count;
    }

    /**
     * Re-check pending mobile-money payments (in case a callback never arrived),
     * and expire ones older than a day.
     */
    public function reconcilePending(): int
    {
        $checked = 0;
        Donation::whereIn('status', ['pending', 'processing'])->whereNotIn('method', ['bank', 'cash'])
            ->where('created_at', '>=', now()->subDay())->where('created_at', '<=', now()->subMinutes(2))
            ->each(function (Donation $donation) use (&$checked) {
                rescue(fn () => $this->donations->sync($donation));
                $checked++;
            });

        Donation::whereIn('status', ['pending', 'processing'])->whereNotIn('method', ['bank'])
            ->where('created_at', '<', now()->subDay())
            ->update(['status' => 'failed', 'failure_reason' => __('Payment timed out.')]);

        return $checked;
    }
}
