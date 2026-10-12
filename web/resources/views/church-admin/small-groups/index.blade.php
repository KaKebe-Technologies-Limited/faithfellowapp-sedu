<x-layouts.church-admin :church="$church" :title="__('Small groups')">
    <x-ff.page-header :title="__('Small groups')" :subtitle="__('Cells, home fellowships and Bible studies. Leaders run attendance and announcements from their group page.')">
        <a href="{{ route('church-admin.small-groups.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('New group') }}</a>
    </x-ff.page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Active groups')" :value="number_format($groups->where('status', 'active')->count())" icon="bi-diagram-3" /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('People in groups')" :value="number_format($groups->sum('members_count'))" icon="bi-people" /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Join requests')" :value="number_format($groups->sum('pending_count'))" icon="bi-person-plus" :primary="$groups->sum('pending_count') > 0" /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Cell giving this month')" :value="ff_money($cellGiving)" icon="bi-heart" /></div>
    </div>

    @if ($mapPoints->isNotEmpty())
        <div class="ff-card ff-card-flush overflow-hidden mb-4">
            <div data-ff-map='@json($mapPoints)' style="height: 300px" role="region" aria-label="{{ __('Map of small groups') }}"></div>
        </div>
    @endif

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Group') }}</th><th>{{ __('Leader') }}</th><th>{{ __('Meets') }}</th><th class="text-end">{{ __('Members') }}</th><th class="text-end">{{ __('Attendance (8 wks)') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($groups as $g)
                        @php($a = $attendance[$g->id] ?? null)
                        <tr>
                            <td>
                                <a href="{{ route('groups.show', $g) }}" class="fw-semibold text-ff-black">{{ $g->name }}</a>
                                <div class="small-xs text-ff-muted">{{ __(\App\Models\SmallGroup::TYPES[$g->type] ?? $g->type) }}@if ($g->branch) · {{ $g->branch->name }}@endif @if ($g->status === 'inactive')· <span class="text-danger">{{ __('Inactive') }}</span>@endif</div>
                            </td>
                            <td class="small">@if ($g->leader)<span class="d-flex align-items-center gap-2"><x-ff.avatar :user="$g->leader" size="xs" /> {{ $g->leader->name }}</span>@else<span class="text-ff-muted">—</span>@endif</td>
                            <td class="small">{{ $g->schedule ?? '—' }}@if ($g->location)<div class="small-xs text-ff-muted">{{ Str::limit($g->location, 40) }}</div>@endif</td>
                            <td class="text-end">{{ $g->members_count }}@if ($g->pending_count) <span class="ff-badge ff-badge-warning" title="{{ __('Join requests') }}">+{{ $g->pending_count }}</span>@endif</td>
                            <td class="text-end">{{ $a && $a->total ? round($a->present / $a->total * 100).'%' : '—' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('groups.show', $g) }}" class="btn btn-sm btn-ff-soft">{{ __('Open') }}</a>
                                <a href="{{ route('church-admin.small-groups.edit', [$church, $g]) }}" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('church-admin.small-groups.destroy', [$church, $g]) }}" class="d-inline" data-ff-confirm="{{ __('Delete this group? Meeting history will be removed.') }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ff.empty-state icon="bi-diagram-3" :title="__('No small groups yet')" :message="__('Create your first cell or fellowship group.')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.church-admin>
