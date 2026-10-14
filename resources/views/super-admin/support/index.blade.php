<x-layouts.super-admin :title="__('Support tickets')">
    <x-ff.page-header :title="__('Support tickets')" :subtitle="__('Questions and problems from churches.')" />

    <div class="d-flex flex-wrap gap-3 justify-content-between mb-3">
        <nav class="ff-pills" aria-label="{{ __('Status') }}">
            @foreach (['active' => __('Active'), 'open' => __('Needs reply'), 'pending' => __('Waiting on church'), 'resolved' => __('Resolved'), 'closed' => __('Closed'), 'all' => __('All')] as $value => $label)
                <a href="{{ route('super-admin.support.index', ['status' => $value]) }}" @class(['ff-pill', 'active' => $status === $value])>
                    {{ $label }}@if (isset($counts[$value]) && $counts[$value]) <span class="opacity-75">{{ $counts[$value] }}</span>@endif
                </a>
            @endforeach
            <a href="{{ route('super-admin.support.index', ['status' => $status, 'mine' => 1]) }}" @class(['ff-pill', 'active' => request('mine')])><i class="bi bi-person"></i> {{ __('Assigned to me') }}</a>
        </nav>
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Subject or FF-00012') }}" aria-label="{{ __('Search tickets') }}">
        </form>
    </div>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Ticket') }}</th><th>{{ __('Church') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Assigned') }}</th><th>{{ __('Last update') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($tickets as $t)
                        <tr>
                            <td><a href="{{ route('super-admin.support.show', $t) }}" class="fw-semibold text-ff-black">{{ $t->subject }}</a><div class="small-xs text-ff-muted">{{ $t->reference }} · {{ __(\App\Models\SupportTicket::CATEGORIES[$t->category]) }} · {{ $t->user?->name }}</div></td>
                            <td class="small">{{ $t->church?->name ?? '—' }}</td>
                            <td><span @class(['ff-badge', 'ff-badge-danger' => $t->priority === 'urgent', 'ff-badge-warning' => $t->priority === 'high'])>{{ __(\App\Models\SupportTicket::PRIORITIES[$t->priority]) }}</span></td>
                            <td class="small">{{ $t->assignee?->name ?? '—' }}</td>
                            <td class="small">{{ $t->last_reply_at?->diffForHumans() }}</td>
                            <td><x-ff.status :status="$t->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ff.empty-state icon="bi-life-preserver" :title="__('No tickets here')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tickets->hasPages())<div class="ff-table-footer">{{ $tickets->links() }}</div>@endif
    </div>
</x-layouts.super-admin>
