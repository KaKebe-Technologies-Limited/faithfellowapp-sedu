<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Giving\RecordDonationRequest;
use App\Models\Church;
use App\Models\Donation;
use App\Services\AnalyticsService;
use App\Services\CampaignService;
use App\Services\DonationService;
use App\Services\FinancialRecords;
use App\Support\CsvExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Finance dashboard, transaction log, refunds, cash entry, exports.
 */
class GivingController extends Controller
{
    public function __construct(protected DonationService $donations, protected AnalyticsService $analytics) {}

    /**
     * Brings the church's giving up to date: waiting mobile money gifts are checked with the gateway (a gift is paid when the giver approves it on the
     * phone, but the app, the callback or the cron job may not have told us), and campaign totals are counted again if they fell behind. Done when the
     * finance pages are opened, at most once a minute, and at once with the Check payments button.
     *
     * @return array{settled: int, campaigns: int}
     */
    protected function bringUpToDate(Church $church, bool $force = false): array
    {
        if (! $force && ! Cache::add('giving-heal:'.$church->id, 1, 60)) {
            return ['settled' => 0, 'campaigns' => 0];
        }

        return [
            'settled' => $this->donations->refreshPending($church->donations(), 10),
            'campaigns' => rescue(fn () => app(CampaignService::class)->healChurch($church), 0, false),
        ];
    }

    /** "Check payments": the gifts that are still waiting are asked about now. */
    public function refresh(Church $church): RedirectResponse
    {
        $result = $this->bringUpToDate($church, true);
        $waiting = $church->donations()->whereIn('status', ['pending', 'processing'])->whereNotIn('method', ['cash', 'bank'])->count();

        return back()->with('success', $result['settled']
            ? trans_choice(':n payment was confirmed and is now in your figures.|:n payments were confirmed and are now in your figures.', $result['settled'], ['n' => $result['settled']]).($waiting ? ' '.__(':n still waiting for the giver to approve on their phone.', ['n' => $waiting]) : '')
            : ($waiting ? __(':n payment is still waiting: the giver has not approved it on their phone yet, or the payment service has not answered.', ['n' => $waiting]) : __('Nothing is waiting. Every payment is up to date.')));
    }

    public function index(Request $request, Church $church): View
    {
        $this->bringUpToDate($church);

        return view('church-admin.giving.index', [
            'church' => $church,
            'finance' => $this->analytics->financeDashboard($church),
            'recent' => $church->donations()->with(['user.media', 'campaign'])->latest()->take(10)->get(),
            'pledges' => $church->pledges()->with(['user', 'campaign'])->whereIn('status', ['active', 'overdue'])->latest()->take(8)->get(),
            'payouts' => $church->payouts()->latest('period_end')->take(5)->get(),
            'hasChannels' => $church->givingChannels()->where('is_active', true)->exists(),
        ]);
    }

    public function transactions(Request $request, Church $church): View
    {
        $this->bringUpToDate($church);

        return view('church-admin.giving.transactions', [
            'church' => $church,
            'donations' => $this->filtered($request, $church)->with(['user', 'campaign', 'recorder', 'branch'])->latest()->paginate(30)->withQueryString(),
            'total' => (float) $this->filtered($request, $church)->where('status', 'succeeded')->sum('amount'),
            'campaigns' => $church->campaigns()->orderBy('title')->pluck('title', 'id'),
            'branches' => $church->branches()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /**
     * Financial records for accounting and monitoring: what came in over a period, clustered by what it was given for (the church's
     * giving reasons, fundraising campaigns, tickets), by payment method and over time, with the ledger behind it.
     */
    public function records(Request $request, Church $church, FinancialRecords $records): View
    {
        [$from, $to, $period] = FinancialRecords::period($request->query('period'), $request->query('from'), $request->query('to'));
        $filters = array_filter($request->only(['reason', 'method', 'campaign']));

        return view('church-admin.giving.records', [
            'church' => $church,
            'report' => $records->build($church, $from, $to, $filters),
            'ledger' => $records->ledger($church, $from, $to, $filters)->reorder('paid_at', 'desc')->paginate(25)->withQueryString(),
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'choices' => $records->choices($church),
        ]);
    }

    /** The records as a file: the ledger (CSV, one row per gift), the summary by reason and method (CSV), or a printable PDF. */
    public function recordsExport(Request $request, Church $church, FinancialRecords $records): StreamedResponse|Response
    {
        [$from, $to] = FinancialRecords::period($request->query('period'), $request->query('from'), $request->query('to'));
        $filters = array_filter($request->only(['reason', 'method', 'campaign']));
        $name = 'financial-records-'.$church->slug.'-'.$from->format('Ymd').'-'.$to->format('Ymd');

        if ($request->query('format') === 'summary' || $request->query('format') === 'pdf') {
            $report = $records->build($church, $from, $to, $filters);

            if ($request->query('format') === 'pdf') {
                return Pdf::loadView('receipts.financial-report', [
                    'church' => $church, 'report' => $report,
                    'ledger' => $records->ledger($church, $from, $to, $filters)->limit(1500)->get(),
                ])->setPaper('a4', 'landscape')->download($name.'.pdf');
            }

            $money = fn ($v) => number_format((float) $v, 2, '.', '');
            $rows = [
                [__('Period'), $from->toDateString().' to '.$to->toDateString(), '', '', '', ''],
                [__('Total received'), '', $report['gifts'], $money($report['gross']), $money($report['fees']), $money($report['net'])],
                [__('Paid-out status'), __('Collected online, not yet paid out to the church'), $report['awaiting_payout']['count'], '', '', $money($report['awaiting_payout']['amount'])],
                ['', '', '', '', '', ''],
                [__('GIVEN FOR'), '', '', '', '', ''],
                ...$report['by_reason']->map(fn ($r) => [$r['label'], $r['kind'], $r['gifts'], $money($r['gross']), $money($r['fees']), $money($r['net'])])->all(),
                ['', '', '', '', '', ''],
                [__('PAYMENT METHOD'), '', '', '', '', ''],
                ...$report['by_method']->map(fn ($r) => [$r['label'], '', $r['gifts'], $money($r['gross']), $money($r['fees']), $money($r['net'])])->all(),
            ];

            return CsvExport::download($name.'-summary.csv', [__('Item'), __('Type'), __('Gifts'), __('Gross'), __('Fees'), __('Net')], $rows);
        }

        return CsvExport::download($name.'-ledger.csv',
            ['Paid at', 'Receipt', 'Giver', 'Phone', 'Given for', 'Campaign', 'Method', 'Amount', 'Fee', 'Net', 'Currency', 'Reference', 'Provider ref', 'Note'],
            $records->ledger($church, $from, $to, $filters)->cursor()->map(fn (Donation $d) => [
                $d->paid_at?->format('Y-m-d H:i'), $d->receipt_number, $d->display_name, $d->is_anonymous ? '' : $d->phone, $d->category_label,
                $d->campaign?->title, $d->method_label, $d->amount, $d->fee, number_format((float) $d->amount - (float) $d->fee, 2, '.', ''), $d->currency, $d->reference, $d->provider_ref, $d->note,
            ]));
    }

    public function record(RecordDonationRequest $request, Church $church): RedirectResponse
    {
        $donation = $this->donations->recordOffline($church, $request->user(), $request->validated());

        return back()->with('success', __('Recorded :amount (receipt :n).', ['amount' => ff_money($donation->amount), 'n' => $donation->receipt_number]));
    }

    public function refund(Request $request, Church $church, Donation $donation): RedirectResponse
    {
        $this->authorize('refund', $donation);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->donations->refund($donation, $data['reason']);

        return back()->with('success', __('Gift refunded.'));
    }

    /**
     * Mark a pending bank transfer as received (manual reconciliation).
     */
    public function confirm(Church $church, Donation $donation): RedirectResponse
    {
        $this->authorize('reconcile', $donation);
        abort_unless($donation->method === 'bank' && in_array($donation->status, ['pending', 'processing'], true), 422);
        $this->donations->markSucceeded($donation, null, ['confirmed_by' => auth()->id()]);
        $donation->update(['reconciled_at' => now()]);

        return back()->with('success', __('Bank transfer confirmed.'));
    }

    public function export(Request $request, Church $church): StreamedResponse|Response
    {
        $query = $this->filtered($request, $church)->with(['user', 'campaign'])->orderBy('created_at');

        if ($request->query('format') === 'pdf') {
            $rows = $query->where('status', 'succeeded')->limit(2000)->get();

            return Pdf::loadView('receipts.giving-report', [
                'church' => $church, 'rows' => $rows,
                'from' => $request->query('from'), 'to' => $request->query('to'),
                'byCategory' => $rows->groupBy('category')->map->sum('amount'),
            ])->setPaper('a4', 'landscape')->download('giving-'.$church->slug.'-'.now()->format('Ymd').'.pdf');
        }

        return CsvExport::download('giving-'.$church->slug.'-'.now()->format('Ymd').'.csv',
            ['Date', 'Receipt', 'Giver', 'Phone', 'Given for', 'Campaign', 'Method', 'Amount', 'Currency', 'Status', 'Reference', 'Provider ref'],
            $query->cursor()->map(fn (Donation $d) => [
                $d->created_at->format('Y-m-d H:i'), $d->receipt_number, $d->display_name, $d->is_anonymous ? '' : $d->phone,
                $d->category_label, $d->campaign?->title, $d->method_label, $d->amount, $d->currency, $d->status, $d->reference, $d->provider_ref,
            ]));
    }

    protected function filtered(Request $request, Church $church): Builder
    {
        return $church->donations()->getQuery()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->when($request->filled('reason'), fn ($q) => $q->where('giving_reason_id', (int) $request->query('reason')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->query('method')))
            ->when($request->filled('campaign'), fn ($q) => $q->where('campaign_id', $request->query('campaign')))
            ->when($request->filled('branch'), fn ($q) => $q->where('branch_id', $request->query('branch')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', Carbon::parse($request->query('from'))->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', Carbon::parse($request->query('to'))->endOfDay()))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', '%'.$request->query('q').'%')
                ->orWhere('provider_ref', 'like', '%'.$request->query('q').'%')
                ->orWhere('receipt_number', 'like', '%'.$request->query('q').'%')
                ->orWhere('phone', 'like', '%'.$request->query('q').'%')
                ->orWhere('giver_name', 'like', '%'.$request->query('q').'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$request->query('q').'%'))));
    }
}
