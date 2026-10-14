<?php

namespace App\Services;

use App\Events\DonationSucceeded;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\Donation;
use App\Models\GivingReason;
use App\Models\RecurringGift;
use App\Models\User;
use App\Services\Payments\PaymentResult;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The giving pipeline:
 *
 *   initiate() → gateway charge → [phone prompt | hosted checkout]
 *   → webhook / status poll → sync() → markSucceeded() (idempotent)
 *   → DonationSucceeded event → receipt, campaign progress, finance alert, pledge/ticket updates
 */
class DonationService
{
    /**
     * Create a pending donation and start the payment.
     *
     * @param  array<string, mixed>  $data  amount, category, method, phone, campaign_id, campaign_team_id, pledge_id, is_anonymous, show_on_leaderboard, note
     * @return array{donation: Donation, result: PaymentResult}
     */
    public function initiate(?User $user, Church $church, array $data): array
    {
        if (! $church->isActive()) {
            throw ValidationException::withMessages(['amount' => __('This church is not accepting gifts right now.')]);
        }

        $method = $data['method'];
        $channel = $church->givingChannels()->where('type', $method)->where('is_active', true)->first();
        if (! $channel && $method !== 'cash') {
            throw ValidationException::withMessages(['method' => __('This church does not accept that payment method.')]);
        }

        if (! empty($data['campaign_id'])) {
            $campaign = Campaign::where('church_id', $church->id)->findOrFail($data['campaign_id']);
            if (! $campaign->isOpen()) {
                throw ValidationException::withMessages(['campaign_id' => __('This campaign is closed.')]);
            }
            $data['category'] = 'campaign';
        }

        // A reason chosen from the church's own list: its name is kept on the gift, and it counts as the reason's category.
        $reason = empty($data['campaign_id']) && ! empty($data['reason_id']) ? GivingReason::where('church_id', $church->id)->active()->find($data['reason_id']) : null;
        if (! empty($data['reason_id']) && empty($data['campaign_id']) && ! $reason) {
            throw ValidationException::withMessages(['reason_id' => __('Choose what your gift is for.')]);
        }
        if ($reason) {
            $data['category'] = $reason->category;
        }

        $donation = Donation::create([
            'church_id' => $church->id,
            'user_id' => $user?->id,
            'campaign_id' => $data['campaign_id'] ?? null,
            'campaign_team_id' => $data['campaign_team_id'] ?? null,
            'pledge_id' => $data['pledge_id'] ?? null,
            'recurring_gift_id' => $data['recurring_gift_id'] ?? null,
            'event_rsvp_id' => $data['event_rsvp_id'] ?? null,
            'small_group_id' => $data['small_group_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'category' => $data['category'],
            'giving_reason_id' => $reason?->id,
            'reason' => $reason?->name,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? config('faithfellows.currency'),
            'method' => $method,
            'phone' => $data['phone'] ?? $user?->phone,
            'status' => 'pending',
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'show_on_leaderboard' => (bool) ($data['show_on_leaderboard'] ?? false),
            'note' => $data['note'] ?? null,
            'country' => $data['country'] ?? null,
            'ip_address' => request()?->ip(),
        ]);

        $donation->returnTo = $data['return_to'] ?? null;
        $result = PaymentService::driver($method)->charge($donation->setRelation('church', $church));
        $this->applyResult($donation, $result);

        return ['donation' => $donation->fresh(), 'result' => $result];
    }

    /**
     * Save a recurring-gift schedule. The first gift is charged right away (by the caller), so the schedule's
     * next run is one period later.
     *
     * @param  array<string, mixed>  $data  recurring (weekly|monthly|quarterly), amount, method, phone, category, campaign_id
     */
    public function scheduleRecurring(User $user, Church $church, array $data): RecurringGift
    {
        $reason = ! empty($data['reason_id']) ? GivingReason::where('church_id', $church->id)->active()->find($data['reason_id']) : null;
        $recurring = RecurringGift::create([
            'church_id' => $church->id, 'user_id' => $user->id, 'campaign_id' => $data['campaign_id'] ?? null, 'giving_reason_id' => $reason?->id,
            'category' => $reason?->category ?? $data['category'] ?? 'tithe', 'amount' => $data['amount'], 'currency' => config('faithfellows.currency'),
            'frequency' => $data['recurring'], 'method' => $data['method'], 'phone' => $data['phone'] ?? null,
            'next_run_on' => today(), 'status' => 'active',
        ]);
        $recurring->update(['next_run_on' => $recurring->nextDateAfter(today()), 'last_run_at' => now()]);

        return $recurring;
    }

    /**
     * Ask the gateway for the latest status (used by the waiting page and webhooks).
     */
    public function sync(Donation $donation): Donation
    {
        if ($donation->isFinal() || in_array($donation->method, ['cash', 'bank'], true)) {
            return $donation;
        }

        $result = PaymentService::driver($donation->method)->verify($donation->provider_ref ?: $donation->reference);
        $this->applyResult($donation, $result);

        return $donation->fresh();
    }

    public function applyResult(Donation $donation, PaymentResult $result): void
    {
        match ($result->status) {
            'succeeded' => $this->markSucceeded($donation, $result->providerRef, $result->payload),
            'failed' => $this->markFailed($donation, $result->message, $result->providerRef),
            'disputed' => $donation->update(['status' => 'disputed']),
            'refunded' => $donation->update(['status' => 'refunded', 'refunded_at' => now()]),
            default => $result->providerRef && ! $donation->provider_ref
                ? $donation->update(['provider_ref' => $result->providerRef, 'status' => 'processing'])
                : null,
        };
    }

    /**
     * Idempotent: a webhook and a status poll racing each other only
     * complete the donation (and fire side effects) once.
     *
     * @param  array<string, mixed>  $payload
     */
    public function markSucceeded(Donation $donation, ?string $providerRef = null, array $payload = []): Donation
    {
        $completed = DB::transaction(function () use ($donation, $providerRef, $payload) {
            $locked = Donation::whereKey($donation->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === 'succeeded') {
                return false;
            }

            // Guard: a provider reference may only ever complete one donation.
            if ($providerRef && Donation::where('provider_ref', $providerRef)->whereKeyNot($locked->id)->exists()) {
                return false;
            }

            $locked->update([
                'status' => 'succeeded',
                'provider_ref' => $providerRef ?? $locked->provider_ref,
                'provider_payload' => $payload ?: $locked->provider_payload,
                'paid_at' => now(),
                'failure_reason' => null,
                'receipt_number' => $locked->receipt_number ?? $this->nextReceiptNumber($locked),
            ]);

            return true;
        });

        $donation->refresh();

        if ($completed) {
            DonationSucceeded::dispatch($donation);
        }

        return $donation;
    }

    public function markFailed(Donation $donation, ?string $reason = null, ?string $providerRef = null): void
    {
        Donation::whereKey($donation->id)->whereNotIn('status', ['succeeded', 'refunded'])->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'provider_ref' => $donation->provider_ref ?? $providerRef,
        ]);
    }

    /**
     * Cash / offline gifts entered by finance staff.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordOffline(Church $church, User $staff, array $data): Donation
    {
        $donation = Donation::create([
            'church_id' => $church->id,
            'user_id' => $data['user_id'] ?? null,
            'giver_name' => $data['giver_name'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'small_group_id' => $data['small_group_id'] ?? null,
            'category' => ! empty($data['campaign_id']) ? 'campaign' : $data['category'],
            'amount' => $data['amount'],
            'currency' => config('faithfellows.currency'),
            'method' => $data['method'] ?? 'cash',
            'status' => 'pending',
            'note' => $data['note'] ?? null,
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'recorded_by' => $staff->id,
        ]);
        Audit::log('donation.recorded', $donation, ff_money($donation->amount).' '.$donation->category, [], ['method' => $donation->method]);

        return $this->markSucceeded($donation, null, ['recorded_by' => $staff->id]);
    }

    public function refund(Donation $donation, string $reason): Donation
    {
        abort_unless($donation->status === 'succeeded', 422, __('Only completed gifts can be refunded.'));

        $result = PaymentService::driver($donation->method)->refund($donation, $donation->provider_ref);
        if ($result->status !== 'refunded') {
            throw ValidationException::withMessages(['refund' => $result->message ?? __('Refund failed.')]);
        }

        DB::transaction(function () use ($donation, $reason) {
            $donation->update(['status' => 'refunded', 'refunded_at' => now(), 'refund_reason' => $reason]);
            if ($donation->pledge) {
                $donation->pledge->decrement('paid_amount', min((float) $donation->amount, (float) $donation->pledge->paid_amount));
            }
        });
        Audit::log('donation.refunded', $donation, $reason, ['status' => 'succeeded'], ['status' => 'refunded']);

        if ($donation->campaign) {
            app(CampaignService::class)->recalculate($donation->campaign);
        }

        return $donation->fresh();
    }

    protected function nextReceiptNumber(Donation $donation): string
    {
        // Derived from the (unique) donation id so concurrent completions can never collide.
        return 'R'.$donation->church_id.'-'.now()->format('y').'-'.str_pad((string) $donation->id, 7, '0', STR_PAD_LEFT);
    }
}
