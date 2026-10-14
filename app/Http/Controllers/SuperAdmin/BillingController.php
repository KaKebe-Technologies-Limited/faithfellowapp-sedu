<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Services\BillingService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Super admin: church subscriptions, invoices and plans.
 */
class BillingController extends Controller
{
    public function __construct(protected BillingService $billing) {}

    public function subscriptions(Request $request): View
    {
        $churches = Church::with(['plan', 'media'])
            ->withSum(['invoices as outstanding' => fn ($q) => $q->whereIn('status', ['unpaid', 'overdue'])], 'total')
            ->when($request->filled('billing'), fn ($q) => $q->where('billing_status', $request->query('billing')))
            ->search($request->query('q'))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('super-admin.billing.subscriptions', [
            'churches' => $churches,
            'plans' => Plan::orderBy('sort')->get(),
            'mrr' => $this->billing->monthlyRecurringRevenue(),
            'counts' => Church::selectRaw('billing_status, count(*) c')->groupBy('billing_status')->pluck('c', 'billing_status'),
        ]);
    }

    public function updateSubscription(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_status' => ['required', Rule::in(['trial', 'active', 'past_due', 'suspended', 'cancelled'])],
            'trial_ends_at' => ['nullable', 'date'],
            'paid_until' => ['nullable', 'date'],
            'domain_addon' => ['boolean'],
        ]);
        $church->update($data);
        Audit::log('billing.subscription_updated', $church, $church->name, [], $data);

        return back()->with('success', __('Subscription updated.'));
    }

    public function invoices(Request $request): View
    {
        return view('super-admin.billing.invoices', [
            'invoices' => SubscriptionInvoice::with('church')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->latest()->paginate(30)->withQueryString(),
            'totals' => SubscriptionInvoice::selectRaw('status, SUM(total) t, COUNT(*) c')->groupBy('status')->get()->keyBy('status'),
            'churches' => Church::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function generate(): RedirectResponse
    {
        $count = $this->billing->generateDueInvoices();

        return back()->with('success', __(':n invoice(s) generated.', ['n' => $count]));
    }

    public function createInvoice(Request $request): RedirectResponse
    {
        $data = $request->validate(['church_id' => ['required', 'exists:churches,id'], 'period_start' => ['required', 'date']]);
        $invoice = $this->billing->createInvoice(Church::findOrFail($data['church_id']), Carbon::parse($data['period_start']));

        return back()->with('success', __('Invoice :n created.', ['n' => $invoice->number]));
    }

    public function markPaid(Request $request, SubscriptionInvoice $invoice): RedirectResponse
    {
        $data = $request->validate(['method' => ['required', 'string', 'max:20'], 'reference' => ['nullable', 'string', 'max:190']]);
        $this->billing->markInvoicePaid($invoice, $data['reference'] ?? 'manual-'.Str::random(6), $data['method']);

        return back()->with('success', __('Invoice marked as paid.'));
    }

    public function void(SubscriptionInvoice $invoice): RedirectResponse
    {
        abort_if($invoice->status === 'paid', 422);
        $invoice->update(['status' => 'void']);
        Audit::log('billing.invoice_voided', $invoice, $invoice->number, [], [], $invoice->church_id);

        return back()->with('success', __('Invoice voided.'));
    }

    public function plans(): View
    {
        return view('super-admin.billing.plans', ['plans' => Plan::withCount('churches')->orderBy('sort')->get()]);
    }

    public function savePlan(Request $request, ?Plan $plan = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'price' => ['required', 'numeric', 'min:0'],
            'interval' => ['required', Rule::in(['month', 'year'])],
            'storage_mb' => ['required', 'integer', 'min:100'],
            'stream_hours' => ['required', 'integer', 'min:0'],
            'max_admins' => ['required', 'integer', 'min:1'],
            'max_branches' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);
        $data['features'] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n/', (string) ($data['features'] ?? '')))));
        $data['slug'] = $plan?->slug ?? Str::slug($data['name']);

        if (! empty($data['is_default'])) {
            Plan::query()->update(['is_default' => false]);
        }
        $plan ? $plan->update($data) : Plan::create($data);
        Audit::log('billing.plan_saved', $plan, $data['name']);

        return back()->with('success', __('Plan saved.'));
    }
}
