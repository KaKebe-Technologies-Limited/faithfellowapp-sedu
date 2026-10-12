<x-layouts.church-admin :church="$church" :title="__('Finance')">
    <x-ff.page-header :title="__('Finance')" :subtitle="__('Real-time giving across all channels.')">
        <a href="{{ route('church-admin.giving.export', [$church, 'from' => now()->startOfMonth()->toDateString()]) }}" class="btn btn-ff-ghost"><i class="bi bi-filetype-csv"></i> CSV</a>
        <a href="{{ route('church-admin.giving.export', [$church, 'format' => 'pdf', 'from' => now()->startOfMonth()->toDateString()]) }}" class="btn btn-ff-ghost"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        @if (auth()->user()->canInChurch($church, 'giving.manage'))
            <button class="btn btn-ff-primary" data-bs-toggle="modal" data-bs-target="#recordGift"><i class="bi bi-plus-lg"></i> {{ __('Record cash / bank gift') }}</button>
        @endif
    </x-ff.page-header>
    @include('church-admin.giving._nav')

    @unless ($hasChannels)
        <div class="alert alert-warning rounded-4 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle"></i> <span>{{ __('Online giving is off. Turn on MTN MoMo, Airtel Money or card under Giving channels.') }}</span>
            <a href="{{ route('church-admin.settings.giving', $church) }}" class="btn btn-sm btn-ff-primary ms-auto">{{ __('Set up') }}</a>
        </div>
    @endunless

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('This month')" :value="ff_money($finance['month'])" icon="bi-cash-coin" :trend="$finance['month_trend']" primary /></div>
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Today')" :value="ff_money($finance['today'])" icon="bi-calendar-day">{{ __('This week: :w', ['w' => ff_money($finance['week'])]) }}</x-ff.stat-card></div>
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('This year')" :value="ff_money($finance['year'])" icon="bi-graph-up">{{ __(':n givers this month', ['n' => $finance['givers_month']]) }}</x-ff.stat-card></div>
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Recurring (per month)')" :value="ff_money($finance['recurring_monthly'])" icon="bi-arrow-repeat">{{ __('Pledges outstanding: :p', ['p' => ff_money($finance['pledged_outstanding'])]) }}</x-ff.stat-card></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <x-ff.card :title="__('Giving trend')" :subtitle="__('Last 12 weeks')">
                <div style="height: 260px"><canvas data-ff-chart="{{ json_encode($finance['trendChart']) }}" role="img" aria-label="{{ __('Giving trend') }}"></canvas></div>
            </x-ff.card>
        </div>
        <div class="col-lg-4">
            <x-ff.card :title="__('By category (this year)')">
                @if ($finance['categoryChart']['data']['labels']->isEmpty())
                    <p class="small text-ff-muted">{{ __('No gifts yet this year.') }}</p>
                @else
                    <div style="height: 260px"><canvas data-ff-chart="{{ json_encode($finance['categoryChart']) }}" role="img" aria-label="{{ __('By category') }}"></canvas></div>
                @endif
            </x-ff.card>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <x-ff.card :title="__('Latest gifts')" flush>
                <x-slot:actions><a href="{{ route('church-admin.giving.transactions', $church) }}" class="small">{{ __('All transactions') }}</a></x-slot:actions>
                <div class="table-responsive">
                    <table class="table ff-table">
                        <tbody>
                            @forelse ($recent as $d)
                                <tr>
                                    <td><div class="d-flex align-items-center gap-2"><x-ff.avatar :user="$d->is_anonymous ? null : $d->user" :name="$d->display_name" size="xs" /><span class="small fw-semibold text-ff-black">{{ $d->display_name }}</span></div></td>
                                    <td class="small">{{ $d->campaign?->title ?? $d->category_label }}</td>
                                    <td class="small">{{ $d->method_label }}</td>
                                    <td class="text-end fw-semibold text-nowrap">{{ ff_money($d->amount) }}</td>
                                    <td><x-ff.status :status="$d->status" /></td>
                                    <td class="small text-ff-muted text-nowrap">{{ $d->created_at->diffForHumans(short: true) }}</td>
                                </tr>
                            @empty
                                <tr><td><x-ff.empty-state icon="bi-cash-coin" :title="__('No gifts yet')" class="border-0" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ff.card>
        </div>
        <div class="col-xl-4 d-grid gap-4 align-content-start">
            <x-ff.card :title="__('By payment method')">
                @forelse ($finance['byMethod'] as $method => $total)
                    <div class="d-flex justify-content-between small py-1"><span>{{ config('faithfellows.payment_methods.'.$method, $method) }}</span><strong class="text-ff-black">{{ ff_money($total) }}</strong></div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No data yet.') }}</p>
                @endforelse
            </x-ff.card>
            <x-ff.card :title="__('Open pledges')">
                @forelse ($pledges as $p)
                    <div class="small mb-2">
                        <div class="d-flex justify-content-between"><span class="fw-semibold text-ff-black">{{ $p->is_anonymous ? __('Anonymous') : $p->user?->name }}</span><span>{{ ff_short_number($p->paid_amount) }} / {{ ff_short_number($p->amount) }}</span></div>
                        <x-ff.progress :value="$p->percent()" size="sm" />
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No open pledges.') }}</p>
                @endforelse
            </x-ff.card>
            <x-ff.card :title="__('Recent payouts')">
                @forelse ($payouts as $payout)
                    <div class="d-flex justify-content-between small py-1"><span>{{ $payout->period_end->format('j M') }}</span><span>{{ ff_money($payout->amount) }}</span><x-ff.status :status="$payout->status" /></div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No payouts yet.') }}</p>
                @endforelse
            </x-ff.card>
        </div>
    </div>

    @include('church-admin.giving._record-modal')
</x-layouts.church-admin>
