<x-layouts.church-admin :church="$church" :title="__('Branches')">
    <x-ff.page-header :title="__('Settings')" />
    @include('church-admin.settings._nav')

    <div class="ff-table-wrap">
        <div class="ff-table-toolbar">
            <div>
                <h2 class="h6 mb-0">{{ __('Branches & campuses') }}</h2>
                <span class="small text-ff-muted">{{ __('One church, many branches — with consolidated giving and per-branch reports.') }}</span>
            </div>
            <a href="{{ route('church-admin.branches.create', $church) }}" class="btn btn-ff-primary btn-sm ms-auto"><i class="bi bi-plus-lg"></i> {{ __('Add branch') }}</a>
        </div>
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Branch') }}</th><th>{{ __('Location') }}</th><th>{{ __('Leader') }}</th><th>{{ __('Services') }}</th><th>{{ __('Staff') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td><strong class="text-ff-black">{{ $branch->name }}</strong> @if ($branch->is_main)<span class="ff-badge ff-badge-primary ms-1">{{ __('Main') }}</span>@endif</td>
                            <td class="small">{{ $branch->address ?: $branch->city ?: '—' }}</td>
                            <td class="small">{{ $branch->leader_name ?: '—' }}</td>
                            <td>{{ $branch->service_times_count }}</td>
                            <td>{{ $branch->staff_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('church-admin.branches.edit', [$church, $branch]) }}" class="btn btn-sm btn-ff-soft">{{ __('Edit') }}</a>
                                @unless ($branch->is_main)
                                    <form method="POST" action="{{ route('church-admin.branches.destroy', [$church, $branch]) }}" class="d-inline" data-ff-confirm="{{ __('Remove this branch?') }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ff.empty-state icon="bi-diagram-2" :title="__('No branches yet')" :message="__('Your main campus is created automatically when the church is approved.')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($branches->hasPages())<div class="ff-table-footer">{{ $branches->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
