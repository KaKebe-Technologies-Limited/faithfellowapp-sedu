<x-layouts.church-admin :church="$church" :title="__('Sermons')">
    <x-ff.page-header :title="__('Sermons')" :subtitle="__('Upload messages, organise series and playlists.')">
        <a href="{{ route('church-admin.sermons.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('New sermon') }}</a>
    </x-ff.page-header>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="ff-table-wrap">
                <form method="GET" class="ff-table-toolbar">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width: 240px" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
                    <select name="status" class="form-select form-select-sm" style="max-width: 150px" aria-label="{{ __('Status') }}">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach (['published', 'scheduled', 'draft'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach
                    </select>
                    <select name="series" class="form-select form-select-sm" style="max-width: 180px" aria-label="{{ __('Series') }}">
                        <option value="">{{ __('Any series') }}</option>
                        @foreach ($series as $s)<option value="{{ $s->id }}" @selected((string) request('series') === (string) $s->id)>{{ $s->title }}</option>@endforeach
                    </select>
                    <button class="btn btn-sm btn-ff-primary">{{ __('Filter') }}</button>
                </form>
                <div class="table-responsive">
                    <table class="table ff-table">
                        <thead><tr><th>{{ __('Sermon') }}</th><th>{{ __('Media') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Plays') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($sermons as $sermon)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-ff-black">{{ $sermon->title }}</div>
                                        <div class="small-xs text-ff-muted">{{ collect([$sermon->preacher, $sermon->series?->title, $sermon->published_at?->format('j M Y')])->filter()->implode(' · ') }}</div>
                                    </td>
                                    <td class="text-nowrap">
                                        @if ($sermon->audio_src)<i class="bi bi-music-note-beamed text-ff-primary" title="{{ __('Audio') }}"></i>@endif
                                        @if ($sermon->video_url || $sermon->video_file_src)<i class="bi bi-camera-video text-ff-primary" title="{{ __('Video') }}"></i>@endif
                                        @if ($sermon->notes_url)<i class="bi bi-file-earmark-pdf text-ff-primary" title="{{ __('Notes') }}"></i>@endif
                                    </td>
                                    <td>
                                        <x-ff.status :status="$sermon->status" />
                                        @if ($sermon->visibility === 'members')<span class="ff-badge">{{ __('Members') }}</span>@endif
                                    </td>
                                    <td class="text-end small">{{ number_format($sermon->views_count) }} / {{ number_format($sermon->listens_count) }}</td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('sermons.show', $sermon) }}" class="btn btn-sm btn-ff-ghost" target="_blank" aria-label="{{ __('View') }}"><i class="bi bi-eye"></i></a>
                                        <a href="{{ route('church-admin.sermons.edit', [$church, $sermon]) }}" class="btn btn-sm btn-ff-soft">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('church-admin.sermons.destroy', [$church, $sermon]) }}" class="d-inline" data-ff-confirm="{{ __('Delete this sermon?') }}">@csrf @method('DELETE')
                                            <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-ff.empty-state icon="bi-mic" :title="__('No sermons yet')" :message="__('Upload your first sermon — audio, video or a YouTube link.')" class="border-0">
                                    <a href="{{ route('church-admin.sermons.create', $church) }}" class="btn btn-ff-primary">{{ __('Upload a sermon') }}</a>
                                </x-ff.empty-state></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($sermons->hasPages())<div class="ff-table-footer">{{ $sermons->links() }}</div>@endif
            </div>
        </div>

        <div class="col-xl-4 d-grid gap-4 align-content-start">
            <x-ff.card :title="__('Series')">
                <form method="POST" action="{{ route('church-admin.series.store', $church) }}" class="d-flex gap-2 mb-3">@csrf
                    <input name="title" class="form-control form-control-sm" placeholder="{{ __('New series title') }}" required aria-label="{{ __('Series title') }}">
                    <button class="btn btn-sm btn-ff-primary text-nowrap">{{ __('Add') }}</button>
                </form>
                @forelse ($series as $s)
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom small">
                        <a href="{{ route('church-admin.sermons.index', [$church, 'series' => $s->id]) }}" class="flex-grow-1 text-ff-black fw-semibold">{{ $s->title }}</a>
                        <span class="text-ff-muted">{{ $s->sermons_count }}</span>
                        <form method="POST" action="{{ route('church-admin.series.destroy', [$church, $s]) }}" data-ff-confirm="{{ __('Delete this series?') }}">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-ff-ghost btn-icon" aria-label="{{ __('Delete') }}"><i class="bi bi-x"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('Group sermons by theme, e.g. “Walking by Faith”.') }}</p>
                @endforelse
            </x-ff.card>

            <x-ff.card :title="__('Playlists')">
                <form method="POST" action="{{ route('church-admin.playlists.store', $church) }}" class="mb-3">@csrf
                    <input name="title" class="form-control form-control-sm mb-2" placeholder="{{ __('Playlist title, e.g. Easter 2026') }}" required aria-label="{{ __('Playlist title') }}">
                    <select name="sermon_ids[]" class="form-select form-select-sm mb-2" multiple size="4" aria-label="{{ __('Sermons') }}">
                        @foreach ($sermons as $sermon)<option value="{{ $sermon->id }}">{{ $sermon->title }}</option>@endforeach
                    </select>
                    <input type="hidden" name="is_public" value="1">
                    <button class="btn btn-sm btn-ff-primary w-100">{{ __('Create playlist') }}</button>
                </form>
                @foreach ($playlists as $playlist)
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom small">
                        <span class="flex-grow-1 fw-semibold text-ff-black">{{ $playlist->title }}</span>
                        <span class="text-ff-muted">{{ trans_choice(':count sermon|:count sermons', $playlist->sermons_count) }}</span>
                        <form method="POST" action="{{ route('church-admin.playlists.destroy', [$church, $playlist]) }}" data-ff-confirm="{{ __('Delete this playlist?') }}">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-ff-ghost btn-icon" aria-label="{{ __('Delete') }}"><i class="bi bi-x"></i></button>
                        </form>
                    </div>
                @endforeach
            </x-ff.card>
        </div>
    </div>
</x-layouts.church-admin>
