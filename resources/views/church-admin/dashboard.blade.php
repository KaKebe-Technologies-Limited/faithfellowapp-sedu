<x-layouts.church-admin :church="$church" :title="__('Dashboard')">
    @php
        $done = collect($checklist)->where('done', true)->count();
        $user = auth()->user();
        $maintenanceNote = rescue(fn () => \App\Models\PlatformSetting::get('maintenance_note'), null, false);
    @endphp

    @if ($maintenanceNote)
        <div class="alert alert-info d-flex gap-2 align-items-start"><i class="bi bi-tools"></i><div>{{ $maintenanceNote }}</div></div>
    @endif

    <div class="ff-page-header">
        <div>
            <div class="ff-breadcrumb">{{ now()->format('l, j F Y') }}</div>
            <h1>{{ __('Welcome back, :name', ['name' => $user->first_name]) }}</h1>
            <p>{{ __('Here is what is happening at :church.', ['church' => $church->name]) }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if (Route::has('church-admin.sermons.create') && $user->canInChurch($church, 'content.manage'))
                <a href="{{ route('church-admin.sermons.create', $church) }}" class="btn btn-ff-outline"><i class="bi bi-mic"></i> {{ __('New sermon') }}</a>
            @endif
            @if (Route::has('church-admin.live.create') && $user->canInChurch($church, 'live.manage'))
                <a href="{{ route('church-admin.live.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-broadcast"></i> {{ __('Go live') }}</a>
            @endif
        </div>
    </div>

    @if ($done < count($checklist))
        <div class="ff-card ff-card-mist mb-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-4">
                    <h2 class="h5 mb-1">{{ __('Finish setting up your church') }}</h2>
                    <p class="small mb-3">{{ __(':done of :total steps complete', ['done' => $done, 'total' => count($checklist)]) }}</p>
                    <x-ff.progress :value="$done / max(1, count($checklist)) * 100" />
                </div>
                <div class="col-lg-8">
                    <div class="row g-2">
                        @foreach ($checklist as $item)
                            <div class="col-md-6">
                                <a href="{{ $item['url'] }}" class="d-flex align-items-center gap-2 bg-white rounded-3 px-3 py-2 small text-ff-black">
                                    <i class="bi {{ $item['done'] ? 'bi-check-circle-fill text-success' : 'bi-circle text-ff-muted' }}"></i>
                                    <span @class(['text-decoration-line-through text-ff-muted' => $item['done']])>{{ $item['label'] }}</span>
                                    <i class="bi bi-chevron-right ms-auto small"></i>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        @forelse ($dashboard['cards'] ?? [] as $card)
            <div class="col-sm-6 col-xl-3">
                <x-ff.stat-card :label="$card['label']" :value="$card['value']" :icon="$card['icon']" :trend="$card['trend'] ?? null" :href="$card['href'] ?? null" :primary="$card['primary'] ?? false">{{ $card['note'] ?? '' }}</x-ff.stat-card>
            </div>
        @empty
            <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Staff members')" :value="$stats['staff']" icon="bi-person-badge" primary /></div>
            <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Branches')" :value="$stats['branches']" icon="bi-diagram-2" /></div>
            <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Weekly services')" :value="$stats['service_times']" icon="bi-clock" /></div>
            <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Storage used')" :value="$stats['storage_mb'].' MB'" icon="bi-hdd" /></div>
        @endforelse
    </div>

    @if (! empty($dashboard['charts']))
        <div class="row g-4 mb-4">
            @foreach ($dashboard['charts'] as $chart)
                <div class="{{ $chart['col'] ?? 'col-lg-6' }}">
                    <x-ff.card :title="$chart['title']" :subtitle="$chart['subtitle'] ?? null">
                        <div style="height: {{ $chart['height'] ?? 260 }}px"><canvas data-ff-chart="{{ json_encode($chart['config']) }}" aria-label="{{ $chart['title'] }}" role="img"></canvas></div>
                    </x-ff.card>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7 d-grid gap-4 align-content-start">
            @foreach ($dashboard['lists'] ?? [] as $list)
                @include($list['view'], $list['data'] ?? [])
            @endforeach
            <x-ff.card :title="__('Recent activity')">
                <x-slot:actions>
                    @if ($user->canInChurch($church, 'roles.manage'))<a href="{{ route('church-admin.audit-logs', $church) }}" class="small">{{ __('Full log') }}</a>@endif
                </x-slot:actions>
                @forelse ($activity as $log)
                    <div class="d-flex gap-3 py-2 border-bottom small align-items-center">
                        <x-ff.avatar :user="$log->user" size="xs" />
                        <span class="flex-grow-1 min-w-0 text-truncate"><strong class="text-ff-black">{{ $log->user?->name ?? __('System') }}</strong> · {{ Str::headline(str_replace('.', ' ', $log->action)) }} @if ($log->description)<span class="text-ff-muted">— {{ $log->description }}</span>@endif</span>
                        <span class="text-ff-muted text-nowrap">{{ $log->created_at->diffForHumans(short: true) }}</span>
                    </div>
                @empty
                    <p class="text-ff-muted small mb-0">{{ __('Changes made by your team will show up here.') }}</p>
                @endforelse
            </x-ff.card>
        </div>
        <div class="col-lg-5 d-grid gap-4 align-content-start">
            <div class="ff-card bg-ff-gradient border-0">
                <h2 class="h6 text-white">{{ __('Your church website') }}</h2>
                <p class="small text-white mb-3" style="opacity:.9">{{ __('Auto-generated and always up to date with your sermons, events and giving.') }}</p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ ff_church_url($church) }}" target="_blank" rel="noopener" class="btn btn-ff-white btn-sm"><i class="bi bi-globe"></i> {{ __('Visit') }}</a>
                    @if ($user->canInChurch($church, 'settings.manage'))
                        <a href="{{ route('church-admin.settings.website', $church) }}" class="btn btn-sm text-white border-white rounded-pill"><i class="bi bi-layout-wtf"></i> {{ __('Edit layout') }}</a>
                    @endif
                </div>
            </div>
            @foreach ($dashboard['side'] ?? [] as $side)
                @include($side['view'], $side['data'] ?? [])
            @endforeach
        </div>
    </div>
</x-layouts.church-admin>
