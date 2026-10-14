<x-layouts.super-admin :title="$hymnal->name">
    <x-ff.page-header :title="$hymnal->name" :subtitle="$hymnal->languageLabel().' · '.trans_choice(':n hymn|:n hymns', $hymnal->hymns_count, ['n' => $hymnal->hymns_count])" :breadcrumbs="[__('Hymn book') => route('super-admin.hymnals.index'), $hymnal->name => null]">
        <a href="{{ route('super-admin.hymnals.edit', $hymnal) }}" class="btn btn-ff-soft"><i class="bi bi-pencil"></i> {{ __('Edit hymnal') }}</a>
        <a href="{{ route('super-admin.hymns.create', $hymnal) }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('Add a hymn') }}</a>
    </x-ff.page-header>

    <form method="GET" class="mb-3" role="search">
        <input type="search" name="q" value="{{ $q }}" class="form-control" style="max-width:360px" placeholder="{{ __('Search by title, author or number…') }}" aria-label="{{ __('Search hymns') }}">
    </form>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table align-middle">
                <thead><tr><th style="width:70px">{{ __('No.') }}</th><th>{{ __('Hymn') }}</th><th>{{ __('Credits') }}</th><th>{{ __('Copyright') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($hymns as $hymn)
                        <tr>
                            <td class="fw-semibold">{{ $hymn->number }}</td>
                            <td><div class="fw-semibold text-ff-black">{{ $hymn->title }}</div><div class="small-xs text-ff-muted">{{ $hymn->section ? $hymn->section.' · ' : '' }}{{ $hymn->first_line }}</div></td>
                            <td class="small">{{ $hymn->author }}@if ($hymn->composer)<div class="small-xs text-ff-muted">{{ __('Music') }}: {{ $hymn->composer }}</div>@endif</td>
                            <td class="small text-nowrap">{{ __(\App\Models\Hymn::COPYRIGHT[$hymn->copyright_status] ?? '') }}</td>
                            <td><x-ff.status :status="$hymn->status" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('super-admin.hymns.edit', [$hymnal, $hymn]) }}" class="btn btn-sm btn-ff-soft">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('super-admin.hymns.destroy', [$hymnal, $hymn]) }}" class="d-inline" data-ff-confirm="{{ __('Delete this hymn?') }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ff.empty-state icon="bi-music-note-list" :title="__('No hymns here')" :message="$q ? __('Nothing matches your search.') : __('Add the first hymn of this hymnal.')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($hymns->hasPages())<div class="ff-table-footer">{{ $hymns->links() }}</div>@endif
    </div>

    @if ($hymnal->source_name)
        <p class="small text-ff-muted mt-3">{{ __('Texts collected from') }} {{ $hymnal->source_name }}@if ($hymnal->source_url) — <a href="{{ $hymnal->source_url }}" target="_blank" rel="noopener">{{ $hymnal->source_url }}</a>@endif</p>
    @endif

    <form method="POST" action="{{ route('super-admin.hymnals.destroy', $hymnal) }}" class="mt-4" data-ff-confirm="{{ __('Delete this hymnal and all its hymns?') }}">@csrf @method('DELETE')
        <button class="btn btn-sm btn-ff-ghost text-danger"><i class="bi bi-trash"></i> {{ __('Delete this hymnal') }}</button>
    </form>
</x-layouts.super-admin>
