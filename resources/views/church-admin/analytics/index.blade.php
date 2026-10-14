<x-layouts.church-admin :church="$church" :title="__('Analytics')">
    <x-ff.page-header :title="__('Analytics')" :subtitle="__('How your church is growing, engaging and giving.')">
        <form method="GET" class="d-flex gap-2">
            <select name="days" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Period') }}">
                @foreach (\App\Http\Controllers\ChurchAdmin\AnalyticsController::RANGES as $value => $label)
                    <option value="{{ $value }}" @selected($days === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('church-admin.analytics.pdf', [$church, 'days' => $days]) }}" class="btn btn-ff-primary"><i class="bi bi-file-earmark-pdf"></i> {{ __('PDF report') }}</a>
        <div class="dropdown">
            <button class="btn btn-ff-soft dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download"></i> {{ __('CSV') }}</button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach (['members' => __('New members'), 'engagement' => __('Content engagement'), 'attendance' => __('Attendance'), 'notifications' => __('Notifications')] + (isset($data['headline']['giving']) ? ['giving' => __('Giving (weekly)')] : []) as $set => $label)
                    <li><a class="dropdown-item" href="{{ route('church-admin.analytics.export', [$church, $set, 'days' => $days]) }}">{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>
    </x-ff.page-header>

    @php
        $cards = [
            'members' => [__('Members'), 'bi-people'],
            'engagement' => [__('Content views'), 'bi-play-circle'],
            'attendance' => [__('Avg weekly attendance'), 'bi-qr-code-scan'],
            'live' => [__('Live peak viewers'), 'bi-broadcast'],
            'prayer' => [__('Prayer requests'), 'bi-chat-heart'],
            'giving' => [__('Giving'), 'bi-cash-coin'],
        ];
    @endphp
    <div class="row g-3 mb-4">
        @foreach ($data['headline'] as $key => $card)
            <div class="col-sm-6 col-xl-{{ count($data['headline']) > 5 ? 2 : 3 }} col-lg-4">
                <x-ff.stat-card :label="$cards[$key][0]" :icon="$cards[$key][1]" :primary="$loop->first"
                    :value="($card['money'] ?? false) ? ff_money($card['value']) : number_format($card['value'])"
                    :trend="$card['trend'] ?? null" :trend-label="__('vs previous period')">{{ $card['note'] ?? '' }}</x-ff.stat-card>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <x-ff.card :title="__('Member growth')" :subtitle="__('New subscribers per week')">
                <div style="height: 260px"><canvas data-ff-chart="{{ json_encode($data['charts']['growth']) }}" role="img" aria-label="{{ __('Member growth chart') }}"></canvas></div>
            </x-ff.card>
        </div>
        <div class="col-lg-4">
            <x-ff.card :title="__('Membership stages')">
                @if ($data['charts']['stages']['data']['labels'])
                    <div style="height: 260px"><canvas data-ff-chart="{{ json_encode($data['charts']['stages']) }}" role="img" aria-label="{{ __('Membership stages chart') }}"></canvas></div>
                @else
                    <p class="small text-ff-muted mb-0">{{ __('No members yet.') }}</p>
                @endif
            </x-ff.card>
        </div>

        <div class="col-lg-8">
            <x-ff.card :title="__('Content engagement')" :subtitle="__('Sermons, devotionals and media per week')">
                <div style="height: 260px"><canvas data-ff-chart="{{ json_encode($data['charts']['engagement']) }}" role="img" aria-label="{{ __('Engagement chart') }}"></canvas></div>
            </x-ff.card>
        </div>
        <div class="col-lg-4">
            <x-ff.card :title="__('Top sermons')" flush>
                <div class="table-responsive">
                    <table class="table ff-table mb-0">
                        <thead><tr><th>{{ __('Sermon') }}</th><th class="text-end"><i class="bi bi-eye" title="{{ __('Views') }}"></i></th><th class="text-end"><i class="bi bi-headphones" title="{{ __('Listens') }}"></i></th></tr></thead>
                        <tbody>
                            @forelse ($data['topSermons'] as $s)
                                <tr><td class="small"><a href="{{ route('sermons.show', $s->id) }}" class="text-ff-black">{{ Str::limit($s->title, 34) }}</a><div class="small-xs text-ff-muted">{{ $s->preacher }}</div></td><td class="text-end">{{ $s->views }}</td><td class="text-end">{{ $s->listens }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="small text-ff-muted">{{ __('No sermon activity yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ff.card>
        </div>

        <div class="col-lg-6">
            <x-ff.card :title="__('Service attendance')" :subtitle="__('QR, location and manual check-ins')">
                <div style="height: 240px"><canvas data-ff-chart="{{ json_encode($data['charts']['attendance']) }}" role="img" aria-label="{{ __('Attendance chart') }}"></canvas></div>
            </x-ff.card>
        </div>
        <div class="col-lg-6">
            <x-ff.card :title="__('Small group attendance')">
                @forelse ($data['groups'] as $g)
                    @php($rate = $g->total ? round($g->present / $g->total * 100) : 0)
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span>{{ $g->name }}</span><strong class="text-ff-black">{{ $rate }}%</strong></div>
                        <x-ff.progress :value="$rate" size="sm" />
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No group meetings recorded in this period.') }}</p>
                @endforelse
            </x-ff.card>
        </div>

        @isset($data['charts']['giving'])
            <div class="col-lg-8">
                <x-ff.card :title="__('Giving')" :subtitle="__('Confirmed gifts per week')">
                    <div style="height: 260px"><canvas data-ff-chart="{{ json_encode($data['charts']['giving']) }}" role="img" aria-label="{{ __('Giving chart') }}"></canvas></div>
                </x-ff.card>
            </div>
            <div class="col-lg-4">
                <x-ff.card :title="__('How people give')">
                    @if ($data['charts']['givingMethods']['data']['labels'])
                        <div style="height: 260px"><canvas data-ff-chart="{{ json_encode($data['charts']['givingMethods']) }}" role="img" aria-label="{{ __('Giving methods chart') }}"></canvas></div>
                    @else
                        <p class="small text-ff-muted mb-0">{{ __('No gifts in this period.') }}</p>
                    @endif
                </x-ff.card>
            </div>
        @endisset

        <div class="col-lg-6">
            <x-ff.card :title="__('Events')" flush>
                <div class="table-responsive">
                    <table class="table ff-table mb-0">
                        <thead><tr><th>{{ __('Event') }}</th><th class="text-end">{{ __('RSVPs') }}</th><th class="text-end">{{ __('Checked in') }}</th></tr></thead>
                        <tbody>
                            @forelse ($data['topEvents'] as $e)
                                <tr><td class="small">{{ Str::limit($e->title, 40) }}<div class="small-xs text-ff-muted">{{ \Illuminate\Support\Carbon::parse($e->starts_at)->format('j M Y') }}</div></td><td class="text-end">{{ $e->rsvps }}</td><td class="text-end">{{ $e->checked_in }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="small text-ff-muted">{{ __('No events in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ff.card>
        </div>
        <div class="col-lg-6">
            <x-ff.card :title="__('Live services')" flush>
                <div class="table-responsive">
                    <table class="table ff-table mb-0">
                        <thead><tr><th>{{ __('Service') }}</th><th class="text-end">{{ __('Peak') }}</th><th class="text-end">{{ __('Unique viewers') }}</th></tr></thead>
                        <tbody>
                            @forelse ($data['liveStreams'] as $s)
                                <tr><td class="small">{{ Str::limit($s->title, 40) }}<div class="small-xs text-ff-muted">{{ \Illuminate\Support\Carbon::parse($s->started_at)->format('j M Y') }}</div></td><td class="text-end">{{ $s->peak_viewers }}</td><td class="text-end">{{ $s->unique }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="small text-ff-muted">{{ __('No live services in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ff.card>
        </div>

        <div class="col-lg-6">
            <x-ff.card :title="__('Notifications delivered')" :subtitle="__('Email, push, SMS and WhatsApp (in-app not counted)')">
                @if ($data['charts']['channels']['data']['labels'])
                    <div style="height: 220px"><canvas data-ff-chart="{{ json_encode($data['charts']['channels']) }}" role="img" aria-label="{{ __('Notification channels chart') }}"></canvas></div>
                @else
                    <p class="small text-ff-muted mb-0">{{ __('Only in-app notifications were sent in this period.') }}</p>
                @endif
            </x-ff.card>
        </div>
        <div class="col-lg-6">
            <x-ff.card :title="__('Delivery health')" flush>
                <div class="table-responsive">
                    <table class="table ff-table mb-0">
                        <thead><tr><th>{{ __('Channel') }}</th><th class="text-end">{{ __('Sent') }}</th><th class="text-end">{{ __('Failed') }}</th><th class="text-end">{{ __('Cost') }}</th></tr></thead>
                        <tbody>
                            @forelse ($data['delivery'] as $d)
                                <tr><td>{{ __(config('faithfellows.notification_channels.'.$d->channel, $d->channel)) }}</td><td class="text-end">{{ number_format($d->sent) }}</td><td class="text-end @if ($d->failed) text-danger @endif">{{ number_format($d->failed) }}</td><td class="text-end">{{ ff_money($d->cost) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="small text-ff-muted">{{ __('Nothing sent yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ff.card>
        </div>
    </div>
</x-layouts.church-admin>
