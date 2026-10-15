<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Services\BillingService;
use App\Services\StreamingService;
use App\Support\MobileMoneyPhone;
use App\Support\StorageQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The church's own FaithFellows plan: the monthly fee and its next payment day, usage, invoices, and paying with mobile money.
 */
class BillingController extends Controller
{
    public function __construct(protected BillingService $billing) {}

    public function index(Church $church): View
    {
        $church->load('plan');

        return view('church-admin.billing.index', [
            'church' => $church,
            'next' => $this->billing->nextPayment($church),
            'plans' => Plan::where('is_active', true)->orderBy('sort')->get(),
            'invoices' => $church->invoices()->latest('period_start')->paginate(12),
            'usage' => [
                'storage' => ['used' => StorageQuota::usedMb($church), 'limit' => StorageQuota::limitMb($church)],
                'stream' => app(StreamingService::class)->monthlyUsage($church),
                'admins' => ['used' => $church->staffMembers()->where('status', 'active')->count(), 'limit' => $church->planLimit('admins') ?? config('faithfellows.limits.admins')],
                'branches' => ['used' => $church->branches()->count(), 'limit' => $church->planLimit('branches') ?? config('faithfellows.limits.branches')],
            ],
        ]);
    }

    public function pay(Request $request, Church $church, SubscriptionInvoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            // The church's fee is paid with mobile money only.
            'method' => ['required', Rule::in(['mtn_momo', 'airtel_money', 'iotec'])],
            'phone' => ['nullable', 'required_if:method,iotec,mtn_momo,airtel_money', MobileMoneyPhone::rule()],
        ]);

        $result = $this->billing->payInvoice($invoice, $data['method'], $data['phone'] ?? null);

        if ($result->status === 'redirect') {
            return redirect()->away($result->redirectUrl);
        }

        return back()->with($result->status === 'failed' ? 'error' : 'info', match ($result->status) {
            'succeeded' => __('Payment received — thank you!'),
            'failed' => $result->message ?? __('Payment failed.'),
            default => __('Approve the prompt on your phone. This page updates when the payment is confirmed.'),
        });
    }

    public function check(Church $church, SubscriptionInvoice $invoice): RedirectResponse
    {
        if ($invoice->method) {
            $this->billing->syncInvoicePayment($invoice, $invoice->method);
        }

        return back()->with('info', $invoice->fresh()->status === 'paid' ? __('Invoice paid.') : __('Still waiting for the payment confirmation.'));
    }

    public function changePlan(Request $request, Church $church): RedirectResponse
    {
        $plan = Plan::where('is_active', true)->findOrFail($request->validate(['plan_id' => ['required', 'integer']])['plan_id']);
        $this->billing->changePlan($church, $plan);

        return back()->with('success', __('Your plan is now :plan. The new price applies from your next invoice.', ['plan' => $plan->name]));
    }

    public function requestDomain(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate(['custom_domain' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i', Rule::unique('churches')->ignore($church->id)]]);
        $church->update(['custom_domain' => strtolower(preg_replace('/^www\./i', '', $data['custom_domain'])), 'domain_addon' => true, 'custom_domain_verified' => false]);

        return back()->with('success', __('Domain requested. Our team will register / connect it and add UGX :p to your next invoice.', ['p' => number_format(config('faithfellows.pricing.domain_price'))]));
    }
}
