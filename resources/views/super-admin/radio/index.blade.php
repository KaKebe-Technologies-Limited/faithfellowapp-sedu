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
        <p class="small text-ff-muted">{{ __('MP3, M4A, AAC or WAV. The title, artist, length and cover picture are read from the file. Up to :limit MB per song. Big songs are sent in pieces, so keep this page open until each one shows a tick. Only upload music you have the right to broadcast.', ['limit' => $limitMb]) }}</p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <input type="file" id="radio-files" class="form-control" accept=".mp3,.m4a,.aac,.wav,audio/*" multiple style="max-width:420px">
            <button type="button" id="radio-send" class="btn btn-ff-primary"><i class="bi bi-cloud-arrow-up"></i> {{ __('Upload') }}</button>
        </div>
        <div id="radio-progress" class="ff-upload-list" aria-live="polite"></div>
        <template id="radio-card">
            <div class="ff-upload is-working">
                <div class="ff-upload-ring" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <svg viewBox="0 0 80 80" aria-hidden="true"><circle class="track" cx="40" cy="40" r="35"/><circle class="arc" cx="40" cy="40" r="35"/></svg>
                    <span class="ff-upload-logo"><x-logo-mark :size="30" class="text-ff-primary" /></span>
                    <span class="ff-upload-state" aria-hidden="true"></span>
                </div>
                <div class="ff-upload-info">
                    <div class="ff-upload-name"></div>
                    <div class="ff-upload-status"></div>
                </div>
                <div class="ff-upload-pct">0%</div>
            </div>
        </template>
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
            // Sends the chosen songs one at a time, each cut into 4 MB pieces: a big song never meets the server's upload size limit,
            // and a piece that fails on a weak connection is simply sent again. Each song gets a card with a ring, the FaithFellows
            // mark in the middle and the percentage, so the person sees how far it has got.
            (() => {
                const input = document.getElementById('radio-files');
                const list = document.getElementById('radio-progress');
                const button = document.getElementById('radio-send');
                const card = document.getElementById('radio-card');
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                const url = @json(route('super-admin.radio.chunk'));
                const PIECE = 4 * 1024 * 1024;
                const LIMIT = {{ (int) $limitMb }} * 1024 * 1024;
                const CIRCLE = 2 * Math.PI * 35;
                const mb = (bytes) => (bytes / 1048576).toFixed(bytes >= 10485760 ? 0 : 1);
                const newId = () => ((crypto.randomUUID ? crypto.randomUUID() : Date.now() + Math.random().toString(36).slice(2)) + 'xxxxxxxxxxxxxxxx').replace(/[^A-Za-z0-9_-]/g, '').slice(0, 40);
                const wait = (ms) => new Promise((r) => setTimeout(r, ms));

                function makeCard(file) {
                    const el = card.content.firstElementChild.cloneNode(true);
                    const ring = el.querySelector('.ff-upload-ring');
                    const arc = el.querySelector('.arc');
                    arc.style.strokeDasharray = CIRCLE;
                    const ui = {
                        el,
                        set(percent, status, state) {
                            const value = Math.max(0, Math.min(100, Math.round(percent)));
                            arc.style.strokeDashoffset = CIRCLE * (1 - value / 100);
                            ring.setAttribute('aria-valuenow', value);
                            el.querySelector('.ff-upload-pct').textContent = value + '%';
                            if (status !== undefined) el.querySelector('.ff-upload-status').textContent = status;
                            if (state) { el.classList.remove('is-working', 'is-adding', 'is-done', 'is-error'); el.classList.add(state); }
                        },
                    };
                    el.querySelector('.ff-upload-name').textContent = file.name;
                    ui.set(0, 'Waiting… ' + mb(file.size) + ' MB', 'is-working');
                    list.appendChild(el);
                    return ui;
                }

                // One piece, with live progress; resolves {ok, status, data}. Tries again when the connection drops or the server hiccups.
                function sendPiece(makeBody, onProgress) {
                    const once = () => new Promise((resolve) => {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', url);
                        xhr.setRequestHeader('X-CSRF-TOKEN', token);
                        xhr.setRequestHeader('Accept', 'application/json');
                        xhr.upload.onprogress = (e) => { if (e.lengthComputable) onProgress(e.loaded, e.total); };
                        xhr.onload = () => { let data = {}; try { data = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ } resolve({ ok: xhr.status >= 200 && xhr.status < 300, status: xhr.status, data }); };
                        xhr.onerror = xhr.ontimeout = () => resolve({ ok: false, status: 0, data: { message: 'the connection was lost' } });
                        xhr.send(makeBody());
                    });
                    return (async () => {
                        let last;
                        for (let attempt = 1; attempt <= 4; attempt++) {
                            last = await once();
                            if (last.ok || (last.status >= 400 && last.status < 500 && ![408, 419, 429].includes(last.status))) return last;
                            await wait(1000 * attempt);
                        }
                        return last;
                    })();
                }

                button.addEventListener('click', async () => {
                    const files = [...input.files];
                    if (!files.length) return input.click();
                    button.disabled = true;
                    list.innerHTML = '';
                    const cards = files.map(makeCard);
                    let added = 0;
                    for (const [n, file] of files.entries()) {
                        const ui = cards[n];
                        if (file.size > LIMIT) { ui.set(0, 'This song is bigger than {{ (int) $limitMb }} MB.', 'is-error'); continue; }
                        const total = Math.max(1, Math.ceil(file.size / PIECE));
                        const id = newId();
                        let outcome = null;
                        ui.set(0, 'Uploading… 0 of ' + mb(file.size) + ' MB', 'is-working');
                        for (let i = 0; i < total; i++) {
                            const slice = file.slice(i * PIECE, (i + 1) * PIECE);
                            const before = i * PIECE;
                            outcome = await sendPiece(() => {
                                const body = new FormData();
                                body.append('upload_id', id);
                                body.append('index', i);
                                body.append('total', total);
                                body.append('name', file.name);
                                body.append('chunk', slice, 'piece');
                                return body;
                            }, (loaded, all) => {
                                // The last piece is on its way in full: now the server joins the song and adds it to the radio.
                                if (i + 1 === total && loaded >= all) { ui.set(100, 'Adding it to the radio…', 'is-adding'); return; }
                                const sent = before + Math.min(slice.size, slice.size * (loaded / all));
                                ui.set(Math.min(99, (sent / file.size) * 100), 'Uploading… ' + mb(sent) + ' of ' + mb(file.size) + ' MB', 'is-working');
                            });
                            if (!outcome.ok) break;
                        }
                        if (outcome?.ok) { ui.set(100, (outcome.data.title || file.name) + ' is on the radio', 'is-done'); added++; }
                        else { ui.set(Number(ui.el.querySelector('.ff-upload-ring').getAttribute('aria-valuenow')) || 0, outcome?.data?.errors?.chunk?.[0] || outcome?.data?.message || 'Could not be added.', 'is-error'); }
                    }
                    button.disabled = false;
                    if (added) setTimeout(() => location.reload(), 1800);
                });
            })();
        </script>
    @endpush
</x-layouts.super-admin>
