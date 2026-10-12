<x-layouts.super-admin :title="__('Analytics')">
    <x-ff.page-header :title="__('Platform analytics')" :subtitle="__('Growth, giving and engagement across every church on FaithFellows.')">
        <form method="GET">
            <select name="days" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Period') }}">
                @foreach (\App\Http\Controllers\ChurchAdmin\AnalyticsController::RANGES as $value => $label)
                    <option value="{{ $value }}" @selected($days === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('super-admin.analytics.pdf', ['days' => $days]) }}" class="btn btn-ff-primary"><i class="bi bi-file-earmark-pdf"></i> {{ __('PDF report') }}</a>
        <a href="{{ route('super-admin.analytics.export', ['days' => $days]) }}" class="btn btn-ff-soft"><i class="bi bi-download"></i> {{ __('Churches CSV') }}</a>
    </x-ff.page-header>

    @php
        $cards = ['churches' => [__('Active churches'), 'bi-buildings'], 'users' => [__('Users'), 'bi-people'], 'giving' => [__('Giving processed'), 'bi-cash-coin'],
            'mrr' => [__('Subscription MRR'), 'bi-graph-up-arrow'], 'streams' => [__('Live services'), 'bi-broadcast'], 'sms' => [__('SMS spend'), 'bi-chat-left-text']];
    @endphp
    <div class="row g-3 mb-4">
        @foreach ($data['headline'] as $key => $card)
            <div class="col-sm-6 col-lg-4 col-xl-2">
                <x-ff.stat-card :label="$cards[$key][0]" :icon="$cards[$key][1]" :primary="$loop->first"
                    :value="($card['money'] ?? false) ? ff_money($card['value']) : number_format($card['value'])"
                    :trend="$card['trend'] ?? null" :trend-label="__('vs previous period')">{{ $card['note'] ?? '' }}</x-ff.stat-card>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <x-ff.card :title="__('Growth')" :subtitle="__('New accounts and church subscriptions per week')">
                <div style="height: 280px"><canvas data-ff-chart="{{ json_encode($data['charts']['growth']) }}" role="img" aria-label="{{ __('Growth chart') }}"></canvas></div>
            </x-ff.card>
        </div>
        <div class="col-lg-4">
            <x-ff.card :title="__('Churches by district')">
                <div style="height: 280px"><canvas data-ff-chart="{{ json_encode($data['charts']['regions']) }}" role="img" aria-label="{{ __('Churches by district chart') }}"></canvas></div>
            </x-ff.card>
        </div>
        <div class="col-lg-8">
            <x-ff.card :title="__('Giving across all churches')">
                <div style="height: 260px"><canvas data-ff-chart="{{ json_encode($data['charts']['giving']) }}" role="img" aria-label="{{ __('Giving chart') }}"></canvas></div>
            </x-ff.card>
        </div>
        <div class="col-lg-4">
            <x-ff.card :title="__('Payment methods')">
                @if ($data['charts']['methods']['data']['labels'])
                    <div style="height: 260px"><canvas data-ff-chart="{{ json_encode($data['charts']['methods']) }}" role="img" aria-label="{{ __('Payment methods chart') }}"></canvas></div>
                @else
                    <p class="small text-ff-muted mb-0">{{ __('No gifts in this period.') }}</p>
                @endif
            </x-ff.card>
        </div>
        <div class="col-lg-8">
            <x-ff.card :title="__('Largest churches')" flush>
                <div class="table-responsive">
                    <table class="table ff-table mb-0">
                        <thead><tr><th>{{ __('Church') }}</th><th>{{ __('City') }}</th><th>{{ __('Billing') }}</th><th class="text-end">{{ __('Members') }}</th><th class="text-end">{{ __('Giving') }}</th></tr></thead>
                        <tbody>
                            @foreach ($data['topChurches'] as $c)
                                <tr><td><a href="{{ route('super-admin.churches.show', $c->id) }}" class="fw-semibold text-ff-black">{{ $c->name }}</a></td><td class="small">{{ $c->city }}</td><td><x-ff.status :status="$c->billing_status" /></td><td class="text-end">{{ number_format($c->members) }}</td><td class="text-end">{{ ff_money($c->giving) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ff.card>
        </div>
        <div class="col-lg-4">
            <x-ff.card :title="__('Notification delivery')" flush>
                <div class="table-responsive">
                    <table class="table ff-table mb-0">
                        <thead><tr><th>{{ __('Channel') }}</th><th class="text-end">{{ __('Sent') }}</th><th class="text-end">{{ __('Failed') }}</th><th class="text-end">{{ __('Cost') }}</th></tr></thead>
                        <tbody>
                            @forelse ($data['channels'] as $d)
                                <tr><td>{{ __(config('faithfellows.notification_channels.'.$d->channel, $d->channel)) }}</td><td class="text-end">{{ number_format($d->sent) }}</td><td class="text-end">{{ number_format($d->failed) }}</td><td class="text-end">{{ ff_money($d->cost) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="small text-ff-muted">{{ __('Nothing delivered yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ff.card>
        </div>
    </div>
</x-layouts.super-admin>
