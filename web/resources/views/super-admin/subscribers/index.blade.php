<x-layouts.super-admin :title="__('Subscribers')">
    <x-ff.page-header :title="__('Subscribers')" :subtitle="__('The people who follow each church, and how every church is doing: payments and activity.')" />

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Followers (all churches)')" :value="number_format($rows->sum('followers'))" icon="bi-people" primary /></div>
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('New in 30 days')" :value="number_format($rows->sum('new_30'))" icon="bi-person-plus" /></div>
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Churches quiet and warned')" :value="$rows->filter(fn ($r) => $r['church']->dormant_warned_at && $r['church']->status === 'active')->count()" icon="bi-moon" /></div>
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Churches blocked for being quiet')" :value="$rows->filter(fn ($r) => $r['church']->dormant_blocked_at && $r['church']->status === 'suspended')->count()" icon="bi-pause-circle" /></div>
    </div>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table align-middle">
                <thead><tr><th>{{ __('Church') }}</th><th class="text-end">{{ __('Followers') }}</th><th class="text-end">{{ __('New (30 days)') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Standing') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($rows->sortByDesc('followers') as $row)
                        @php($c = $row['church'])
                        <tr>
                            <td><div class="d-flex align-items-center gap-2"><x-ff.church-avatar :church="$c" size="sm" /><a href="{{ route('super-admin.churches.show', $c) }}" class="fw-semibold text-ff-black">{{ $c->name }}</a></div></td>
                            <td class="text-end fw-semibold">{{ number_format($row['followers']) }}</td>
                            <td class="text-end">{{ $row['new_30'] > 0 ? '+'.number_format($row['new_30']) : '0' }}</td>
                            <td class="small">
                                <x-ff.status :status="$c->billing_status ?? 'trial'" />
                                <div class="small-xs text-ff-muted">{{ ($c->billing_status === 'trial' ? __('free until :d', ['d' => $c->trial_ends_at?->format('j M Y')]) : ($c->paid_until ? __('paid until :d', ['d' => $c->paid_until->format('j M Y')]) : '')) }}</div>
                            </td>
                            <td class="small">
                                @if ($c->status === 'suspended' && $c->dormant_blocked_at)<span class="ff-badge ff-badge-danger">{{ __('Blocked: quiet') }}</span>
                                @elseif ($c->status === 'suspended')<span class="ff-badge ff-badge-danger">{{ __('Suspended') }}</span>
                                @elseif ($c->dormant_warned_at)<span class="ff-badge ff-badge-warning">{{ __('Warned: quiet since :d', ['d' => $c->dormant_warned_at->format('j M')]) }}</span>
                                @else<span class="ff-badge ff-badge-success">{{ __('Active') }}</span>@endif
                            </td>
                            <td class="text-end"><a href="{{ route('super-admin.subscribers.show', $c) }}" class="btn btn-sm btn-ff-soft">{{ __('Open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.super-admin>
