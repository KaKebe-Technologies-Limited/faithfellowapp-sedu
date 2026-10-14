@php
    $cur = $report['currency'];
    $export = fn ($format = null) => route('church-admin.giving.records.export', array_filter(['church' => $church, 'period' => $period, 'from' => $period === 'custom' ? $from->toDateString() : null, 'to' => $period === 'custom' ? $to->toDateString() : null, 'format' => $format] + $filters));
@endphp
<x-layouts.church-admin :church="$church" :title="__('Financial records')">
    <x-ff.page-header :title="__('Finance')" :subtitle="__('Records for accounting and monitoring: what came in, what for, and how.')">
        <a href="{{ $export() }}" class="btn btn-ff-ghost"><i class="bi bi-filetype-csv"></i> {{ __('Ledger (CSV)') }}</a>
        <a href="{{ $export('summary') }}" class="btn btn-ff-ghost"><i class="bi bi-table"></i> {{ __('Summary (CSV)') }}</a>
        <a href="{{ $export('pdf') }}" class="btn btn-ff-ghost"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    </x-ff.page-header>
    @include('church-admin.giving._nav')

    <form method="GET" class="ff-table-toolbar mb-3 bg-white rounded-4 p-3 shadow-sm" x-data="{ period: '{{ $period }}' }">
        <select name="period" class="form-select form-select-sm" style="max-width: 170px" x-model="period" aria-label="{{ __('Period') }}">
            @foreach (\App\Services\FinancialRecords::PERIODS as $key => $label)<option value="{{ $key }}">{{ __($label) }}</option>@endforeach
        </select>
        <template x-if="period === 'custom'">
            <span class="d-flex gap-2">
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm" aria-label="{{ __('From') }}">
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm" aria-label="{{ __('To') }}">
            </span>
        </template>
        <select name="reason" class="form-select form-select-sm" style="max-width: 190px" aria-label="{{ __('Given for') }}">
            <option value="">{{ __('Everything given for') }}</option>
            @foreach ($choices['reasons'] as $id => $name)<option value="{{ $id }}" @selected((string) ($filters['reason'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach
            @foreach ($choices['campaigns'] as $id => $title)<option value="campaign:{{ $id }}" @selected(($filters['reason'] ?? '') === 'campaign:'.$id)>{{ __('Fundraising: :t', ['t' => $title]) }}</option>@endforeach
        </select>
        <select name="method" class="form-select form-select-sm" style="max-width: 170px" aria-label="{{ __('Method') }}">
            <option value="">{{ __('Any method') }}</option>
            @foreach (config('faithfellows.payment_methods') as $k => $l)<option value="{{ $k }}" @selected(($filters['method'] ?? '') === $k)>{{ __($l) }}</option>@endforeach
        </select>
        <button class="btn btn-sm btn-ff-primary">{{ __('Show') }}</button>
        <span class="ms-auto small text-ff-muted">{{ $from->format('j M Y') }} – {{ $to->format('j M Y') }}</span>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Received (gross)')" :value="ff_money($report['gross'], $cur)" icon="bi-cash-coin" primary>{{ trans_choice(':n gift|:n gifts', $report['gifts'], ['n' => number_format($report['gifts'])]) }} · {{ __(':n givers', ['n' => number_format($report['givers'])]) }}</x-ff.stat-card></div>
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Fees to payment companies')" :value="ff_money($report['fees'], $cur)" icon="bi-percent">{{ $report['gross'] > 0 ? __(':p% of what was received', ['p' => round($report['fees'] / $report['gross'] * 100, 1)]) : __('No fees yet') }}</x-ff.stat-card></div>
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Net to the church')" :value="ff_money($report['net'], $cur)" icon="bi-bank">{{ __('Average gift :a', ['a' => ff_money($report['average'], $cur)]) }}</x-ff.stat-card></div>
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Waiting to be paid out')" :value="ff_money($report['awaiting_payout']['amount'], $cur)" icon="bi-hourglass-split">{{ trans_choice(':n online gift not yet paid out|:n online gifts not yet paid out', $report['awaiting_payout']['count'], ['n' => $report['awaiting_payout']['count']]) }}</x-ff.stat-card></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <x-ff.card :title="__('Received over time')" :subtitle="$report['series']['data']['labels'] && count($report['series']['data']['labels']) > 62 ? __('By month') : __('By day')">
                <div style="height: 260px"><canvas data-ff-chart="{{ json_encode($report['series']) }}" role="img" aria-label="{{ __('Received over time') }}"></canvas></div>
            </x-ff.card>
        </div>
        <div class="col-lg-4">
            <x-ff.card :title="__('Still to settle')">
                <ul class="list-unstyled small mb-0 d-grid gap-2">
                    <li class="d-flex justify-content-between"><span>{{ __('Pending (not yet approved)') }}</span><strong>{{ ff_money($report['pending']['amount'], $cur) }} <span class="text-ff-muted fw-normal">({{ $report['pending']['count'] }})</span></strong></li>
                    <li class="d-flex justify-content-between"><span>{{ __('Refunded') }}</span><strong>{{ ff_money($report['refunded']['amount'], $cur) }} <span class="text-ff-muted fw-normal">({{ $report['refunded']['count'] }})</span></strong></li>
                    <li class="d-flex justify-content-between"><span>{{ __('Failed or cancelled') }}</span><strong>{{ $report['failed'] }}</strong></li>
                </ul>
                <p class="small text-ff-muted mt-3 mb-0">{{ __('Pending gifts are waiting for the giver to enter their PIN. Only gifts that succeeded count as money received.') }}</p>
            </x-ff.card>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <x-ff.card :title="__('Given for')" :subtitle="__('Every gift is counted under what it was given for')" flush>
                <div class="table-responsive">
                    <table class="table ff-table mb-0">
                        <thead><tr><th>{{ __('For') }}</th><th class="text-end">{{ __('Gifts') }}</th><th class="text-end">{{ __('Received') }}</th><th class="text-end">{{ __('Fees') }}</th><th class="text-end">{{ __('Net') }}</th><th style="width: 22%">{{ __('Share') }}</th></tr></thead>
                        <tbody>
                            @forelse ($report['by_reason'] as $row)
                                <tr>
                                    <td class="fw-semibold text-ff-black">{{ $row['label'] }}@if ($row['kind'] === 'campaign')<span class="badge text-bg-light ms-1">{{ __('Fundraising') }}</span>@endif</td>
                                    <td class="text-end">{{ number_format($row['gifts']) }}</td>
                                    <td class="text-end fw-semibold text-nowrap">{{ ff_money($row['gross'], $cur) }}</td>
                                    <td class="text-end text-ff-muted text-nowrap">{{ ff_money($row['fees'], $cur) }}</td>
                                    <td class="text-end text-nowrap">{{ ff_money($row['net'], $cur) }}</td>
                                    <td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height: 6px" role="progressbar" aria-valuenow="{{ $row['share'] }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ $row['share'] }}%; background: var(--ff-primary, #BC2649)"></div></div><span class="small-xs text-ff-muted">{{ $row['share'] }}%</span></div></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-ff.empty-state icon="bi-cash-coin" :title="__('No gifts in this period')" class="border-0" /></td></tr>
                            @endforelse
                        </tbody>
                        @if ($report['by_reason']->isNotEmpty())
                            <tfoot><tr class="fw-bold"><td>{{ __('Total') }}</td><td class="text-end">{{ number_format($report['gifts']) }}</td><td class="text-end text-nowrap">{{ ff_money($report['gross'], $cur) }}</td><td class="text-end text-nowrap">{{ ff_money($report['fees'], $cur) }}</td><td class="text-end text-nowrap">{{ ff_money($report['net'], $cur) }}</td><td></td></tr></tfoot>
                        @endif
                    </table>
                </div>
            </x-ff.card>
        </div>
        <div class="col-xl-5">
            <x-ff.card :title="__('Payment method')" flush>
                <div class="table-responsive">
                    <table class="table ff-table mb-0">
                        <thead><tr><th>{{ __('Method') }}</th><th class="text-end">{{ __('Gifts') }}</th><th class="text-end">{{ __('Received') }}</th><th class="text-end">{{ __('Net') }}</th></tr></thead>
                        <tbody>
                            @forelse ($report['by_method'] as $row)
                                <tr><td class="fw-semibold text-ff-black">{{ __($row['label']) }}</td><td class="text-end">{{ number_format($row['gifts']) }}</td><td class="text-end text-nowrap">{{ ff_money($row['gross'], $cur) }}</td><td class="text-end text-nowrap">{{ ff_money($row['net'], $cur) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="text-center small text-ff-muted py-4">{{ __('Nothing received in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ff.card>
        </div>
    </div>

    <x-ff.card :title="__('Ledger')" :subtitle="__('Every gift behind the numbers, newest first')" flush>
        <div class="table-responsive">
            <table class="table ff-table mb-0">
                <thead><tr><th>{{ __('Paid') }}</th><th>{{ __('Receipt') }}</th><th>{{ __('Giver') }}</th><th>{{ __('Given for') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Fee') }}</th><th class="text-end">{{ __('Net') }}</th></tr></thead>
                <tbody>
                    @forelse ($ledger as $d)
                        <tr>
                            <td class="small text-nowrap">{{ $d->paid_at?->format('j M Y H:i') }}</td>
                            <td class="small-xs text-ff-muted">{{ $d->receipt_number ?? $d->reference }}</td>
                            <td class="small"><span class="fw-semibold text-ff-black">{{ $d->display_name }}</span>@if (! $d->is_anonymous && $d->phone)<div class="small-xs text-ff-muted">{{ $d->phone }}</div>@endif</td>
                            <td class="small">{{ $d->campaign ? __('Fundraising: :t', ['t' => $d->campaign->title]) : $d->category_label }}</td>
                            <td class="small">{{ $d->method_label }}</td>
                            <td class="text-end fw-semibold text-nowrap">{{ ff_money($d->amount, $d->currency) }}</td>
                            <td class="text-end text-ff-muted text-nowrap">{{ ff_money($d->fee, $d->currency) }}</td>
                            <td class="text-end text-nowrap">{{ ff_money($d->amount - $d->fee, $d->currency) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-ff.empty-state icon="bi-journal-text" :title="__('No gifts in this period')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($ledger->hasPages())<div class="ff-table-footer">{{ $ledger->links() }}</div>@endif
    </x-ff.card>
</x-layouts.church-admin>
