<x-layouts.super-admin :title="__('Gospel radio')">
    <x-ff.page-header :title="__('Gospel radio')" :subtitle="__('Songs that play round the clock in the app, in a loop. Everyone hears the same song at the same moment, like a radio station.')" />

    <div class="row g-4 mb-4">
        {{-- What is on air --}}
        <div class="col-lg-7">
            <x-ff.card :title="__('On air now')" :subtitle="$now ? __('Live from the station clock') : __('Nothing is playing')">
                @if ($now)
                    <div class="d-flex align-items-center gap-3">
                        <img src="{{ $now['track']['cover_url'] }}" alt="" class="rounded-3 ff-cover" style="width:96px;height:96px;object-fit:cover">
                        <div class="flex-grow-1 min-w-0">
                            <div class="fs-5 fw-semibold text-ff-black text-truncate">{{ $now['track']['title'] }}</div>
                            <div class="text-ff-muted text-truncate">{{ $now['track']['artist'] ?: '—' }}</div>
                            <div class="small-xs text-ff-muted mt-1">{{ __('Started :time', ['time' => \Illuminate\Support\Carbon::parse($now['started_at'])->diffForHumans()]) }} · {{ __('then') }} <strong>{{ $now['upcoming'][0]['title'] ?? '' }}</strong></div>
                        </div>
                    </div>
                @else
                    <p class="text-ff-muted mb-0">{{ $enabled ? __('Add at least one song below and the radio starts straight away.') : __('The radio is switched off, so the app hides the player.') }}</p>
                @endif
                <hr>
                <div class="d-flex flex-wrap gap-4 small">
                    <div><div class="text-ff-muted">{{ __('Songs in the loop') }}</div><div class="fw-semibold fs-5">{{ $tracks->where('is_active', true)->count() }}</div></div>
                    <div><div class="text-ff-muted">{{ __('One round lasts') }}</div><div class="fw-semibold fs-5">{{ $loop >= 3600 ? floor($loop / 3600).' h '.floor(($loop % 3600) / 60).' min' : floor($loop / 60).' min' }}</div></div>
                    <div><div class="text-ff-muted">{{ __('Rounds a day') }}</div><div class="fw-semibold fs-5">{{ $loop ? number_format(86400 / $loop, 1) : '—' }}</div></div>
                </div>
                @if ($loop && $loop < 3600)
                    <div class="alert alert-warning small mt-3 mb-0"><i class="bi bi-info-circle"></i> {{ __('The loop is under an hour, so listeners will hear the same songs often. Add more songs for variety.') }}</div>
                @endif
            </x-ff.card>
        </div>

        {{-- Station settings --}}
        <div class="col-lg-5">
            <form method="POST" action="{{ route('super-admin.radio.settings') }}" class="ff-card">@csrf @method('PUT')
                <h2 class="h6 mb-3">{{ __('Station') }}</h2>
                <x-ff.input name="name" :label="__('Station name')" :value="old('name', $name)" :help="__('Shown on the player in the app.')" />
                <x-ff.checkbox name="enabled" :label="__('Radio is on')" :checked="$enabled" switch :help="__('Turn off to hide the player from every phone.')" />
                <button class="btn btn-ff-primary">{{ __('Save') }}</button>
            </form>
        </div>
    </div>

    {{-- Add songs --}}
    <div class="ff-card mb-4" id="radio-upload">
        <h2 class="h6 mb-1">{{ __('Add songs') }}</h2>
        <p class="small text-ff-muted">{{ __('MP3, M4A, AAC or WAV. The title, artist, length and cover picture are read from the file. Up to :limit per song. Only upload music you have the right to broadcast.', ['limit' => $limit]) }}</p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <input type="file" id="radio-files" class="form-control" accept=".mp3,.m4a,.aac,.wav,audio/*" multiple style="max-width:420px">
            <button type="button" id="radio-send" class="btn btn-ff-primary"><i class="bi bi-cloud-arrow-up"></i> {{ __('Upload') }}</button>
        </div>
        <ul id="radio-progress" class="list-unstyled small mt-3 mb-0"></ul>
        @error('track')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>

    {{-- The loop --}}
    <div class="ff-table-wrap">
        <div class="ff-table-toolbar"><strong>{{ __('The loop, in order') }}</strong></div>
        <div class="table-responsive">
            <table class="table ff-table align-middle">
                <thead><tr><th style="width:56px">#</th><th>{{ __('Song') }}</th><th>{{ __('Length') }}</th><th>{{ __('On air') }}</th><th>{{ __('Preview') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($tracks as $track)
                        <tr @class(['text-ff-muted' => ! $track->is_active])>
                            <td class="fw-semibold">{{ $loop->iteration }}</td>
                            <td>
                                <form method="POST" action="{{ route('super-admin.radio.update', $track) }}" class="d-flex align-items-center gap-2">@csrf @method('PUT')
                                    <img src="{{ $track->coverUrl() }}" alt="" class="rounded-2 ff-cover" style="width:48px;height:48px;object-fit:cover">
                                    <div class="d-flex flex-column gap-1 flex-grow-1">
                                        <input name="title" value="{{ $track->title }}" class="form-control form-control-sm" aria-label="{{ __('Title') }}" required maxlength="160">
                                        <input name="artist" value="{{ $track->artist }}" class="form-control form-control-sm" placeholder="{{ __('Artist') }}" aria-label="{{ __('Artist') }}" maxlength="160">
                                    </div>
                                    <button class="btn btn-sm btn-ff-soft">{{ __('Save') }}</button>
                                </form>
                            </td>
                            <td class="small text-nowrap">{{ floor($track->duration_seconds / 60) }}:{{ str_pad($track->duration_seconds % 60, 2, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                <form method="POST" action="{{ route('super-admin.radio.update', $track) }}">@csrf @method('PUT')
                                    <input type="hidden" name="is_active" value="{{ $track->is_active ? 0 : 1 }}">
                                    <button class="btn btn-sm {{ $track->is_active ? 'btn-ff-primary' : 'btn-ff-ghost' }}">{{ $track->is_active ? __('Playing') : __('Off') }}</button>
                                </form>
                            </td>
                            <td><audio controls preload="none" src="{{ $track->streamUrl() }}" style="height:34px;max-width:220px"></audio></td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('super-admin.radio.move', $track) }}" class="d-inline">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Move up') }}" @disabled($loop->first)><i class="bi bi-arrow-up"></i></button></form>
                                <form method="POST" action="{{ route('super-admin.radio.move', $track) }}" class="d-inline">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Move down') }}" @disabled($loop->last)><i class="bi bi-arrow-down"></i></button></form>
                                <form method="POST" action="{{ route('super-admin.radio.destroy', $track) }}" class="d-inline" data-ff-confirm="{{ __('Remove this song from the radio?') }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Remove') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ff.empty-state icon="bi-music-note-beamed" :title="__('No songs yet')" :message="__('Upload the first song above and the radio goes on air.')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            // Sends the chosen songs one at a time, so a long list never hits the server's upload limit.
            (() => {
                const input = document.getElementById('radio-files');
                const list = document.getElementById('radio-progress');
                const button = document.getElementById('radio-send');
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                button.addEventListener('click', async () => {
                    const files = [...input.files];
                    if (!files.length) return input.click();
                    button.disabled = true;
                    let added = 0;
                    for (const file of files) {
                        const row = document.createElement('li');
                        row.textContent = file.name + ' … uploading';
                        list.appendChild(row);
                        const body = new FormData();
                        body.append('track', file);
                        try {
                            const res = await fetch(@json(route('super-admin.radio.store')), { method: 'POST', body, headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } });
                            const data = await res.json().catch(() => ({}));
                            if (res.ok) { row.textContent = '✓ ' + (data.title || file.name) + ' added'; row.className = 'text-success'; added++; }
                            else { row.textContent = '✗ ' + file.name + ': ' + (data.errors?.track?.[0] || data.message || 'could not be added'); row.className = 'text-danger'; }
                        } catch (e) { row.textContent = '✗ ' + file.name + ': the upload failed'; row.className = 'text-danger'; }
                    }
                    button.disabled = false;
                    if (added) setTimeout(() => location.reload(), 900);
                });
            })();
        </script>
    @endpush
</x-layouts.super-admin>
