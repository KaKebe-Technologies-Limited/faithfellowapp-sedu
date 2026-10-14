<x-layouts.super-admin :title="__('Churches')">
    <x-ff.page-header :title="__('Churches')" :subtitle="__('Approve, suspend and support every church on the platform.')">
        <a href="{{ route('super-admin.churches.create') }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('Onboard a church') }}</a>
    </x-ff.page-header>

    <div class="ff-table-wrap">
        <div class="ff-table-toolbar">
            <div class="ff-pills">
                <a href="{{ route('super-admin.churches.index') }}" @class(['ff-pill', 'active' => ! $status])>{{ __('All') }} <span class="opacity-75">{{ $counts->sum() }}</span></a>
                @foreach (\App\Models\Church::STATUSES as $s)
                    <a href="{{ route('super-admin.churches.index', ['status' => $s]) }}" @class(['ff-pill', 'active' => $status === $s])>{{ __(ucfirst($s)) }} <span class="opacity-75">{{ $counts[$s] ?? 0 }}</span></a>
                @endforeach
            </div>
            <form class="ms-auto" method="GET" role="search">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search churches…') }}" aria-label="{{ __('Search churches') }}">
            </form>
        </div>
        <div class="table-responsive">
            <table class="table ff-table">
                <thead>
                    <tr><th>{{ __('Church') }}</th><th>{{ __('Location') }}</th><th>{{ __('Admin') }}</th><th>{{ __('Staff') }}</th><th>{{ __('Status') }}</th><th>{{ __('Joined') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($churches as $church)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <x-ff.church-avatar :church="$church" size="sm" />
                                    <div class="min-w-0">
                                        <a href="{{ route('super-admin.churches.show', $church) }}" class="fw-semibold text-ff-black">{{ $church->name }}</a>
                                        <div class="small-xs text-ff-muted">{{ $church->denomination }}</div>
                                    </div>
                                    @if ($church->is_featured)<i class="bi bi-star-fill text-warning" title="{{ __('Featured') }}"></i>@endif
                                </div>
                            </td>
                            <td class="small">{{ $church->location }}</td>
                            <td class="small">{{ $church->owner?->name }}<div class="small-xs text-ff-muted">{{ $church->owner?->email }}</div></td>
                            <td>{{ $church->staff_members_count }}</td>
                            <td><x-ff.status :status="$church->status" /></td>
                            <td class="small text-ff-muted">{{ $church->created_at->format('j M Y') }}</td>
                            <td class="text-end text-nowrap">
                                @if ($church->status === 'active')<a href="{{ route('super-admin.churches.manage', $church) }}" class="btn btn-sm btn-ff-primary"><i class="bi bi-box-arrow-in-right"></i> {{ __('Manage') }}</a>@endif
                                <a href="{{ route('super-admin.churches.show', $church) }}" class="btn btn-sm btn-ff-soft">{{ __('Open') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ff.empty-state icon="bi-buildings" :title="__('No churches yet')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($churches->hasPages())<div class="ff-table-footer">{{ $churches->links() }}</div>@endif
    </div>
</x-layouts.super-admin>
