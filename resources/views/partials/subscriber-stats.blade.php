@php
    $t = $stats['totals'];
    $a = $stats['activity'];
    $bar = fn ($rows) => collect($rows)->max('count') ?: 1;
@endphp
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Followers')" :value="number_format($t['followers'])" icon="bi-people" primary>{{ __(':n waiting for approval', ['n' => $t['pending']]) }} · {{ __(':n left', ['n' => $t['left']]) }}</x-ff.stat-card></div>
    <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('New in 30 days')" :value="number_format($t['new_30'])" icon="bi-person-plus" :trend="$t['growth']" :trend-label="__('vs the 30 days before')">{{ __(':n in the last 7 days', ['n' => $t['new_7']]) }}</x-ff.stat-card></div>
    <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Opened the app (30 days)')" :value="$a['opened_30_percent'].'%'" icon="bi-phone">{{ __(':a of :b followers · :c this week', ['a' => number_format($a['opened_30']), 'b' => number_format($t['followers']), 'c' => number_format($a['opened_7'])]) }}</x-ff.stat-card></div>
    <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Gave in 90 days')" :value="$a['gave_90_percent'].'%'" icon="bi-heart">{{ __(':n followers gave', ['n' => number_format($a['gave_90'])]) }}</x-ff.stat-card></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <x-ff.card :title="__('New followers each week')" :subtitle="__('The last 12 weeks')">
            <div style="height: 240px"><canvas data-ff-chart="{{ json_encode($stats['weekly']) }}" role="img" aria-label="{{ __('New followers each week') }}"></canvas></div>
        </x-ff.card>
    </div>
    <div class="col-lg-4">
        <x-ff.card :title="__('What followers did in the app')" :subtitle="__('The last 30 days, all followers together')">
            <ul class="list-unstyled mb-0 d-grid gap-3 small">
                <li class="d-flex justify-content-between"><span><i class="bi bi-book text-ff-primary"></i> {{ __('Time reading the Bible') }}</span><strong>{{ number_format($a['read_minutes_30']) }} {{ __('min') }}</strong></li>
                <li class="d-flex justify-content-between"><span><i class="bi bi-headphones text-ff-primary"></i> {{ __('Time listening (radio, podcasts)') }}</span><strong>{{ number_format($a['listen_minutes_30']) }} {{ __('min') }}</strong></li>
                <li class="d-flex justify-content-between"><span><i class="bi bi-share text-ff-primary"></i> {{ __('Scriptures and messages shared') }}</span><strong>{{ number_format($a['shares_30']) }}</strong></li>
            </ul>
            <p class="small text-ff-muted mt-3 mb-0">{{ __('Figures are added up; nobody is named.') }}</p>
        </x-ff.card>
    </div>
</div>

<div class="row g-4 mb-4">
    @foreach ([
        __('By stage') => $stats['stages'],
        __('By age') => $stats['ages'],
        __('Men and women') => $stats['genders'],
        __('Where they joined from') => $stats['sources'],
        __('Towns') => $stats['towns'],
        __('Branches') => $stats['branches'],
    ] as $heading => $rows)
        @continue(empty($rows) || collect($rows)->sum('count') === 0)
        <div class="col-md-6 col-xl-4">
            <x-ff.card :title="$heading">
                <div class="d-grid gap-2">
                    @foreach ($rows as $row)
                        <div>
                            <div class="d-flex justify-content-between small"><span>{{ $row['label'] }}</span><strong>{{ number_format($row['count']) }}</strong></div>
                            <div class="progress" style="height: 6px" role="progressbar" aria-valuenow="{{ $row['count'] }}" aria-valuemin="0" aria-valuemax="{{ $bar($rows) }}"><div class="progress-bar" style="width: {{ $row['count'] / $bar($rows) * 100 }}%; background: var(--ff-primary, #BC2649)"></div></div>
                        </div>
                    @endforeach
                </div>
            </x-ff.card>
        </div>
    @endforeach
</div>

<x-ff.card :title="__('Newest followers')" flush>
    <div class="table-responsive">
        <table class="table ff-table mb-0">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Joined') }}</th><th>{{ __('Stage') }}</th><th>{{ __('Town') }}</th></tr></thead>
            <tbody>
                @forelse ($stats['recent'] as $r)
                    <tr><td class="fw-semibold text-ff-black">{{ $r['name'] }}</td><td class="small">{{ \Illuminate\Support\Carbon::parse($r['joined'])->format('j M Y') }}</td><td class="small">{{ ucfirst(str_replace('_', ' ', $r['stage'])) }}</td><td class="small">{{ $r['city'] ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="4"><x-ff.empty-state icon="bi-people" :title="__('No followers yet')" :message="__('Share your church page or QR code so people can follow you in the app.')" class="border-0" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-ff.card>
