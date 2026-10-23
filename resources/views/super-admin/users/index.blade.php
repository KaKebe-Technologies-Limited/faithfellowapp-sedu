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
                <thead><tr><th>{{ __('User') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Churches') }}</th><th>{{ __('Joined') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($users as $u)
                        <tr>
                            <td><a href="{{ route('super-admin.users.show', $u) }}" class="d-flex align-items-center gap-2 text-ff-black"><x-ff.avatar :user="$u" size="sm" /><span><span class="fw-semibold d-block">{{ $u->name }}</span><span class="small-xs text-ff-muted">{{ $u->email }}@unless ($u->email_verified_at) · <span class="text-warning-emphasis">{{ __('unverified') }}</span>@endunless</span></span></a></td>
                            <td class="small">{{ $u->phone ?? '—' }}</td>
                            <td class="small">{{ $u->roles->pluck('name')->map(fn ($r) => Str::headline($r))->implode(', ') }}</td>
                            <td class="small">
                                @if ($u->subscriptions->isEmpty())
                                    <span class="text-ff-muted">—</span>
                                @else
                                    {{ $u->subscriptions->take(2)->map(fn ($s) => $s->church?->name)->filter()->implode(', ') }}@if ($u->subscriptions->count() > 2) <span class="text-ff-muted">+{{ $u->subscriptions->count() - 2 }}</span>@endif
                                @endif
                            </td>
                            <td class="small">{{ $u->created_at->format('j M Y') }}</td>
                            <td><x-ff.status :status="$u->status" /></td>
                            <td class="text-end text-nowrap">
                                @if ($u->status !== 'suspended')
                                    <button type="button" class="btn btn-sm btn-ff-soft" data-bs-toggle="modal" data-bs-target="#assignModal"
                                        data-action="{{ route('super-admin.users.churches.assign', $u) }}" data-name="{{ $u->name }}" data-followed="{{ $u->subscriptions->pluck('church_id')->toJson() }}">title="{{ __('Assign to a church') }}" aria-label="{{ __('Assign :name to a church', ['name' => $u->name]) }}"><i class="bi bi-person-plus"></i> {{ __('Assign') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ff.empty-state icon="bi-people" :title="__('No users found')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="ff-table-footer">{{ $users->links() }}</div>@endif
    </div>

    {{-- Assign a user to a church, or two: tick them, press Assign --}}
    <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <form method="POST" action="#" class="modal-content" id="assignForm">@csrf
                <div class="modal-header">
                    <h2 class="modal-title h5" id="assignTitle">{{ __('Assign to a church') }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-ff-muted"><strong id="assignName"></strong> {{ __('will follow every church you tick, at once. It does not count against the three churches a member can follow, and the church’s approval is not asked for.') }}</p>
                    @include('super-admin.users._church-list', ['churches' => $churches])
                    <div class="row g-2 mt-2">
                        <div class="col-7">
                            <label class="form-label small fw-semibold" for="assign-stage">{{ __('They are') }}</label>
                            <select id="assign-stage" name="stage" class="form-select form-select-sm">@foreach (\App\Models\Subscription::STAGES as $key => $label)<option value="{{ $key }}" @selected($key === 'member')>{{ __($label) }}</option>@endforeach</select>
                        </div>
                        <div class="col-5 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="notify" value="1" id="assign-notify" checked><label class="form-check-label small" for="assign-notify">{{ __('Tell them') }}</label></div></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ff-ghost" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button class="btn btn-ff-primary"><i class="bi bi-person-plus"></i> {{ __('Assign') }}</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            // Opens the window for the user whose button was pressed: the churches they already follow are ticked and cannot be ticked again.
            const assignModal = document.getElementById('assignModal');
            assignModal?.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const followed = JSON.parse(button.dataset.followed || '[]');
                document.getElementById('assignForm').action = button.dataset.action;
                document.getElementById('assignName').textContent = button.dataset.name;
                assignModal.querySelectorAll('[data-church]').forEach((row) => {
                    const on = followed.includes(Number(row.dataset.church));
                    const box = row.querySelector('input[type=checkbox]');
                    box.checked = on;
                    box.disabled = on;
                    row.querySelector('[data-following]').classList.toggle('d-none', !on);
                });
                const filter = document.getElementById('church-filter');
                if (filter) { filter.value = ''; filter.dispatchEvent(new Event('input')); }
            });
        </script>
    @endpush
</x-layouts.super-admin>
