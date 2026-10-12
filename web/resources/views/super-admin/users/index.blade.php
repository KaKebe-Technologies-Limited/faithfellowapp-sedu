<x-layouts.super-admin :title="__('Users')">
    <x-ff.page-header :title="__('Users')" :subtitle="__('Everyone with a FaithFellows account.')">
        <a href="{{ route('super-admin.users.export', request()->query()) }}" class="btn btn-ff-soft"><i class="bi bi-download"></i> {{ __('Export CSV') }}</a>
    </x-ff.page-header>

    <div class="d-flex flex-wrap gap-3 justify-content-between mb-3">
        <nav class="ff-pills" aria-label="{{ __('Filter') }}">
            @foreach (['' => [__('All'), 'all'], 'admins' => [__('Admins'), 'admins'], 'unverified' => [__('Unverified'), 'unverified'], 'suspended' => [__('Suspended'), 'suspended']] as $value => [$label, $countKey])
                <a href="{{ route('super-admin.users.index', array_filter(['filter' => $value, 'q' => request('q')])) }}" @class(['ff-pill', 'active' => request('filter', '') === $value])>{{ $label }} <span class="opacity-75">{{ number_format($counts[$countKey]) }}</span></a>
            @endforeach
        </nav>
        <form method="GET" class="d-flex gap-2">
            @if (request('filter'))<input type="hidden" name="filter" value="{{ request('filter') }}">@endif
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Name, email or phone') }}" aria-label="{{ __('Search users') }}">
        </form>
    </div>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('User') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Roles') }}</th><th class="text-end">{{ __('Churches') }}</th><th>{{ __('Joined') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($users as $u)
                        <tr>
                            <td><a href="{{ route('super-admin.users.show', $u) }}" class="d-flex align-items-center gap-2 text-ff-black"><x-ff.avatar :user="$u" size="sm" /><span><span class="fw-semibold d-block">{{ $u->name }}</span><span class="small-xs text-ff-muted">{{ $u->email }}@unless ($u->email_verified_at) · <span class="text-warning-emphasis">{{ __('unverified') }}</span>@endunless</span></span></a></td>
                            <td class="small">{{ $u->phone ?? '—' }}</td>
                            <td class="small">{{ $u->roles->pluck('name')->map(fn ($r) => Str::headline($r))->implode(', ') }}</td>
                            <td class="text-end">{{ $u->subscriptions_count }}</td>
                            <td class="small">{{ $u->created_at->format('j M Y') }}</td>
                            <td><x-ff.status :status="$u->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ff.empty-state icon="bi-people" :title="__('No users found')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="ff-table-footer">{{ $users->links() }}</div>@endif
    </div>
</x-layouts.super-admin>
