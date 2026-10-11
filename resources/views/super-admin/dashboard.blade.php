<x-layouts.super-admin :title="__('Dashboard')">
    <x-ff.page-header :title="__('Platform overview')" :subtitle="__('Everything happening across FaithFellows.')" />

    <div class="row g-3 mb-4">
        @isset($stats['churches'])
            <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Active churches')" :value="number_format($stats['churches'])" icon="bi-buildings" primary /></div>
        @endisset
        @isset($stats['pending'])
            <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Pending approval')" :value="number_format($stats['pending'])" icon="bi-hourglass-split" :href="Route::has('super-admin.churches.index') ? route('super-admin.churches.index', ['status' => 'pending']) : null" /></div>
        @endisset
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Users')" :value="number_format($stats['users'])" icon="bi-people">{{ __(':n new in 30 days', ['n' => $stats['new_users']]) }}</x-ff.stat-card></div>
        @isset($stats['giving'])
            <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Giving (30 days)')" :value="ff_money($stats['giving'])" icon="bi-cash-coin" /></div>
        @endisset
        @isset($stats['mrr'])
            <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Subscription revenue (MRR)')" :value="ff_money($stats['mrr'])" icon="bi-graph-up-arrow" /></div>
        @endisset
        @isset($stats['tickets'])
            <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Open support tickets')" :value="number_format($stats['tickets'])" icon="bi-life-preserver" /></div>
        @endisset
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('New contact messages')" :value="number_format($stats['messages'])" icon="bi-inbox" :href="Route::has('super-admin.messages.index') ? route('super-admin.messages.index') : null" /></div>
    </div>

    @isset($pendingChurchList)
        @if ($pendingChurchList->isNotEmpty())
            <x-ff.card :title="__('Awaiting approval')" class="mb-4">
                <div class="table-responsive">
                    <table class="table ff-table">
                        <tbody>
                            @foreach ($pendingChurchList as $church)
                                <tr>
                                    <td><div class="d-flex align-items-center gap-2"><x-ff.church-avatar :church="$church" size="sm" /><strong class="text-ff-black">{{ $church->name }}</strong></div></td>
                                    <td class="text-ff-muted small">{{ $church->city }} · {{ $church->denomination }}</td>
                                    <td class="text-ff-muted small">{{ $church->created_at->diffForHumans() }}</td>
                                    <td class="text-end"><a href="{{ route('super-admin.churches.show', $church) }}" class="btn btn-sm btn-ff-primary">{{ __('Review') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ff.card>
        @endif
    @endisset

    <div class="row g-4">
        <div class="col-lg-5">
            <x-ff.card :title="__('Newest users')">
                <ul class="list-unstyled mb-0">
                    @foreach ($recentUsers as $u)
                        <li class="d-flex align-items-center gap-3 py-2">
                            <x-ff.avatar :user="$u" size="sm" />
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-semibold text-ff-black small text-truncate">{{ $u->name }}</div>
                                <div class="small-xs text-ff-muted text-truncate">{{ $u->email }}</div>
                            </div>
                            <span class="small-xs text-ff-muted">{{ $u->created_at->diffForHumans(short: true) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-ff.card>
        </div>
        <div class="col-lg-7">
            <x-ff.card :title="__('Recent admin activity')">
                @forelse ($recentActivity as $log)
                    <div class="d-flex gap-3 py-2 border-bottom small">
                        <code class="text-ff-primary">{{ $log->action }}</code>
                        <span class="flex-grow-1 text-truncate">{{ $log->description }}</span>
                        <span class="text-ff-muted text-nowrap">{{ $log->user?->name ?? __('System') }} · {{ $log->created_at->diffForHumans(short: true) }}</span>
                    </div>
                @empty
                    <x-ff.empty-state icon="bi-clipboard-data" :title="__('No activity yet')" :message="__('Admin actions will appear here.')" />
                @endforelse
            </x-ff.card>
        </div>
    </div>
</x-layouts.super-admin>
