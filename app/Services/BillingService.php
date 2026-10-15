<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Notifications\Billing\InvoiceIssued;
use App\Notifications\Billing\InvoicePaid;
use App\Notifications\Billing\PaymentDueSoon;
use App\Notifications\Billing\SubscriptionExpiring;
use App\Services\Payments\PaymentResult;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Church plan billing (paid to Kakebe Technologies).
 *
 *  - First 12 months: free hosting (trial). Invoices start after trial_ends_at.
 *  - Monthly invoice for the plan (+ optional domain add-on yearly).
 *  - Unpaid after due date → overdue; after the grace period → church suspended.
 *  - Paying an overdue invoice reactivates the church.
 */
class BillingService
{
    public function startTrial(Church $church): void
    {
        $church->forceFill([
            'plan_id' => $church->plan_id ?? Plan::default()?->id,
            'billing_status' => 'trial',
            'trial_ends_at' => $church->trial_ends_at ?? today()->addMonths((int) rescue(
                fn () => PlatformSetting::get('trial_months', config('faithfellows.pricing.free_hosting_months')),
                config('faithfellows.pricing.free_hosting_months'),
                false,
            )),
        ])->save();
    }

    /**
     * Create the next invoice for every church whose paid period is ending.
     */
    public function generateDueInvoices(?Carbon $today = null): int
    {
        $today ??= today();
        $count = 0;

        Church::with('plan')->where('status', '!=', 'archived')->whereNotNull('plan_id')
            ->where(fn ($q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', $today->copy()->addDays(7)))
            ->each(function (Church $church) use ($today, &$count) {
                $periodStart = ($church->paid_until ?? $church->trial_ends_at ?? $today)->copy();
                if ($periodStart->gt($today->copy()->addDays(7))) {
                    return; // Not due yet (we invoice 7 days ahead)
                }
                if ($church->invoices()->whereDate('period_start', $periodStart)->exists()) {
                    return;
                }
                $this->createInvoice($church, $periodStart);
                $count++;
            });

        return $count;
    }

    public function createInvoice(Church $church, Carbon $periodStart): SubscriptionInvoice
    {
        $plan = $church->plan ?? Plan::default();
        $periodEnd = $plan->interval === 'year' ? $periodStart->copy()->addYear()->subDay() : $periodStart->copy()->addMonthNoOverflow()->subDay();

        $items = [['description' => $plan->name.' plan ('.$periodStart->format('j M').' – '.$periodEnd->format('j M Y').')', 'amount' => (float) $plan->price]];

        // Custom domain add-on billed once a year, on the anniversary month.
        if ($church->domain_addon && ! $church->invoices()->where('created_at', '>=', now()->subYear())->where('items', 'like', '%domain%')->exists()) {
            $items[] = ['description' => 'Custom domain (1 year) — '.$church->custom_domain, 'amount' => (float) config('faithfellows.pricing.domain_price')];
        }

        $total = collect($items)->sum('amount');

        $invoice = $church->invoices()->create([
            'plan_id' => $plan->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'items' => $items,
            'subtotal' => $total,
            'total' => $total,
            'currency' => $plan->currency,
            'due_date' => $periodStart->copy()->addDays(7),
            'status' => 'unpaid',
        ]);
        Audit::log('billing.invoice_created', $invoice, $invoice->number, [], [], $church->id);

        return $invoice;
    }

    /**
     * Daily: mark overdue invoices, remind, and suspend after the grace period.
     *
     * @return array{overdue: int, suspended: int}
     */
    public function enforce(): array
    {
        $overdue = SubscriptionInvoice::where('status', 'unpaid')->whereDate('due_date', '<', today())->update(['status' => 'overdue']);
        $suspended = 0;
        $grace = (int) config('faithfellows.pricing.grace_days');

        SubscriptionInvoice::with('church.owner')->where('status', 'overdue')->get()->each(function (SubscriptionInvoice $invoice) use ($grace, &$suspended) {
            $church = $invoice->church;
            if (! $church) {
                return;
            }
            if ($church->billing_status !== 'past_due' && $church->billing_status !== 'suspended') {
                $church->update(['billing_status' => 'past_due']);
            }
            if (! $invoice->reminded_at || $invoice->reminded_at->lt(now()->subDays(3))) {
                $church->owner?->notify(new SubscriptionExpiring($church, $invoice));
                $invoice->update(['reminded_at' => now()]);
            }
            if ($invoice->due_date->copy()->addDays($grace)->isPast() && $church->status === 'active') {
                $church->update(['status' => 'suspended', 'billing_status' => 'suspended', 'status_reason' => __('Unpaid invoice :n', ['n' => $invoice->number])]);
                Audit::log('billing.church_suspended', $church, $invoice->number);
                $suspended++;
            }
        });

        // A week before the payment day the church is told what and when; the day before, reminded once more.
        SubscriptionInvoice::with('church')->where('status', 'unpaid')->whereNull('issued_notified_at')->each(function (SubscriptionInvoice $invoice) {
            if ($invoice->church && $invoice->period_start->lte(today()->addDays(7))) {
                Notification::send($invoice->church->billingContacts(), new InvoiceIssued($invoice->church, $invoice));
                $invoice->forceFill(['issued_notified_at' => now()])->save();
            }
        });
        SubscriptionInvoice::with('church')->where('status', 'unpaid')->whereNull('due_soon_notified_at')->whereNotNull('issued_notified_at')->where('issued_notified_at', '<=', now()->subDays(2))
            ->whereDate('period_start', '<=', today()->addDay())->each(function (SubscriptionInvoice $invoice) {
                if ($invoice->church) {
                    Notification::send($invoice->church->billingContacts(), new PaymentDueSoon($invoice->church, $invoice));
                    $invoice->forceFill(['due_soon_notified_at' => now()])->save();
                }
            });

        // Churches whose trial is ending in 7 days get a heads-up.
        Church::with('owner')->where('billing_status', 'trial')->whereDate('trial_ends_at', today()->addDays(7))->each(
            fn (Church $church) => $church->owner?->notify(new SubscriptionExpiring($church, null))
        );

        return ['overdue' => $overdue, 'suspended' => $suspended];
    }

    public function payInvoice(SubscriptionInvoice $invoice, string $method, ?string $phone = null): PaymentResult
    {
        abort_unless($invoice->isPayable(), 422, __('This invoice is already settled.'));

        $invoice->loadMissing('church');
        if ($phone) {
            $invoice->church->phone = $phone; // used by paymentPhone() for this charge only
        }
        $result = PaymentService::driver($method)->charge($invoice);
        $invoice->update(['method' => $method, 'provider_ref' => $result->providerRef ?? $invoice->provider_ref]);
        $this->syncInvoicePayment($invoice, $method, $result);

        return $result;
    }

    public function syncInvoicePayment(SubscriptionInvoice $invoice, string $method, ?PaymentResult $result = null): void
    {
        if ($invoice->status === 'paid') {
            return;
        }
        if (! $result || $result->status === 'pending' || $result->message === 'callback') {
            if (! $invoice->provider_ref) {
                return;
            }
            $result = PaymentService::driver($method)->verify($invoice->provider_ref);
        }
        if ($result->isSuccessful()) {
            $this->markInvoicePaid($invoice, $result->providerRef);
        }
    }

    public function markInvoicePaid(SubscriptionInvoice $invoice, ?string $providerRef = null, ?string $method = null): void
    {
        $paid = DB::transaction(function () use ($invoice, $providerRef, $method) {
            $invoice = SubscriptionInvoice::whereKey($invoice->id)->lockForUpdate()->first();
            if ($invoice->status === 'paid') {
                return;
            }
            $invoice->update(['status' => 'paid', 'paid_at' => now(), 'provider_ref' => $providerRef ?? $invoice->provider_ref, 'method' => $method ?? $invoice->method]);

            $church = $invoice->church;
            $paidUntil = max($church->paid_until?->timestamp ?? 0, $invoice->period_end->copy()->addDay()->timestamp);
            $hasOtherOverdue = $church->invoices()->whereKeyNot($invoice->id)->where('status', 'overdue')->exists();

            $church->update([
                'paid_until' => Carbon::createFromTimestamp($paidUntil)->toDateString(),
                'billing_status' => $hasOtherOverdue ? 'past_due' : 'active',
                'status' => $church->billing_status === 'suspended' && ! $hasOtherOverdue ? 'active' : $church->status,
                'status_reason' => $hasOtherOverdue ? $church->status_reason : null,
            ]);
            Audit::log('billing.invoice_paid', $invoice, $invoice->number, [], [], $church->id);

            return [$church->fresh(), $invoice->fresh()];
        });

        // Thank the church (outside the transaction, so a mail problem never undoes a payment).
        if ($paid) {
            rescue(fn () => Notification::send($paid[0]->billingContacts(), new InvoicePaid($paid[0], $paid[1])), report: true);
        }
    }

    /**
     * What the church sees as its next payment: the date, the amount and how many days are left. It is the open invoice when one has been
     * issued, otherwise the day the paid period or the free period ends, at the plan's fee.
     *
     * @return array{date: ?Carbon, days: ?int, amount: float, currency: string, interval: string, invoice: ?SubscriptionInvoice, state: string}
     */
    public function nextPayment(Church $church): array
    {
        $plan = $church->plan ?? Plan::default();
        $open = $church->invoices()->whereIn('status', ['unpaid', 'overdue'])->orderBy('period_start')->first();
        $date = $open?->period_start ?? $church->paid_until ?? $church->trial_ends_at;

        return [
            'date' => $date?->copy(),
            'days' => $date ? (int) today()->diffInDays($date, false) : null,
            'amount' => (float) ($open?->total ?? $plan?->price ?? config('faithfellows.pricing.plan_price')),
            'currency' => $open?->currency ?? $plan?->currency ?? config('faithfellows.currency'),
            'interval' => $plan?->interval ?? 'month',
            'invoice' => $open,
            'state' => $church->status === 'suspended' ? 'suspended' : ($open?->status === 'overdue' ? 'overdue' : ($church->billing_status === 'trial' ? 'free' : 'paid')),
        ];
    }

    public function changePlan(Church $church, Plan $plan): void
    {
        $old = $church->plan?->name;
        $church->update(['plan_id' => $plan->id]);
        Audit::log('billing.plan_changed', $church, ($old ?? '—').' → '.$plan->name);
    }

    public function monthlyRecurringRevenue(): float
    {
        return (float) Church::where('status', 'active')->whereIn('billing_status', ['active', 'past_due'])
            ->join('plans', 'plans.id', '=', 'churches.plan_id')
            ->sum(DB::raw("CASE WHEN plans.interval = 'year' THEN plans.price / 12 ELSE plans.price END"));
    }
}
