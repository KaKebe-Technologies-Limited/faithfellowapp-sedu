<x-layouts.super-admin :title="__('Radio')">
    <x-ff.page-header :title="__('Radio')" :subtitle="__('Channels that play round the clock in the app, in a loop. Everyone hears the same thing at the same moment, like a radio station.')" />

    {{-- Broadcast to everyone: interrupts every channel in the app --}}
    <div class="ff-card mb-4 {{ $broadcast ? 'border-danger' : '' }}">
        <h2 class="h6 mb-1"><i class="bi bi-broadcast text-ff-primary"></i> {{ __('Broadcast to everyone') }}</h2>
        @if ($broadcast)
            <div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0 mt-2">
                <div>
                    <strong>{{ __('On air for everyone now:') }}</strong> {{ $broadcast['track']->title }}
                    @if ($broadcast['message'])<div class="small">{{ $broadcast['message'] }}</div>@endif
                    <div class="small">{{ __('Ends at :time (East Africa Time). Every channel, the church radios too, is playing it.', ['time' => \Illuminate\Support\Carbon::createFromTimestamp($broadcast['ends'])->format('g:i:s a')]) }}</div>
                </div>
                <form method="POST" action="{{ route('super-admin.radio.broadcast.stop') }}" data-ff-confirm="{{ __('Stop the broadcast? Every channel goes back to what it had on.') }}">@csrf @method('DELETE')<button class="btn btn-danger"><i class="bi bi-stop-fill"></i> {{ __('Stop broadcast') }}</button></form>
            </div>
        @else
            <p class="small text-ff-muted">{{ __('Play one of the platform’s tracks to every listener at once. It interrupts the Gospel radio, the Podcast radio and every church radio, and each channel carries on where it was when the track ends. People who are not listening can be told with a notification.') }}</p>
            @if ($broadcastTracks->isEmpty())
                <div class="small text-ff-muted">{{ __('Add a track to the Gospel radio or the Podcast radio first; you can switch it off in the loop and still broadcast it.') }}</div>
            @else
                <form method="POST" action="{{ route('super-admin.radio.broadcast') }}" class="row g-2 align-items-end" data-ff-confirm="{{ __('Interrupt every radio in the app for everyone listening?') }}">@csrf
                    <div class="col-lg-4">
                        <label class="form-label small fw-semibold" for="broadcast-track">{{ __('Track') }}</label>
                        <select id="broadcast-track" name="track" class="form-select" required>
                            @foreach ($broadcastTracks->groupBy(fn ($t) => $t->channel()->slug) as $slug => $group)
                                <optgroup label="{{ $slug === 'podcast' ? __('Podcast radio') : __('Gospel radio') }}">
                                    @foreach ($group as $t)<option value="{{ $t->id }}">{{ $t->title }}{{ $t->artist ? ' · '.$t->artist : '' }} ({{ gmdate($t->duration_seconds >= 3600 ? 'G:i:s' : 'i:s', $t->duration_seconds) }}){{ $t->is_active ? '' : ' · '.__('off the loop') }}</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label small fw-semibold" for="broadcast-message">{{ __('Message (optional)') }}</label>
                        <input id="broadcast-message" name="message" maxlength="140" class="form-control" placeholder="{{ __('Shown on the radio card, and in the notification') }}">
                    </div>
                    <div class="col-lg-2">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="notify" value="1" id="broadcast-notify" checked><label class="form-check-label small" for="broadcast-notify">{{ __('Notify every phone') }}</label></div>
                    </div>
                    <div class="col-lg-2 text-lg-end"><button class="btn btn-ff-primary"><i class="bi bi-broadcast"></i> {{ __('Broadcast now') }}</button></div>
                </form>
            @endif
        @endif
    </div>

    {{-- The platform's channels --}}
    <ul class="nav nav-pills gap-2 mb-4">
        @foreach ($platformStations as $s)
            <li class="nav-item">
                <a href="{{ route('super-admin.radio.index', ['station' => $s->slug]) }}" @class(['nav-link', 'active' => $s->slug === $station->slug])>
                    <i class="bi {{ $s->kind === 'music' ? 'bi-music-note-beamed' : 'bi-mic' }}"></i>
                    {{ $s->isGospel() ? __('Gospel radio') : __('Podcast radio') }}
                </a>
            </li>
        @endforeach
    </ul>

    @if ($station->slug === 'podcast')
        <div class="alert alert-info small"><i class="bi bi-info-circle"></i> {{ __('The Podcast radio also plays whenever the Gospel radio or a church channel has nothing on (no songs, or switched off), so the Radio button in the app always has something to play.') }}</div>
    @endif

    @include('partials.radio-manager', [
        'station' => $station,
        'tracks' => $tracks,
        'now' => $now,
        'name' => $name,
        'enabled' => $enabled,
        'loopSeconds' => $loopSeconds,
        'limitMb' => $limitMb,
        'stationField' => $station->slug,
        'urls' => ['settings' => route('super-admin.radio.settings'), 'chunk' => route('super-admin.radio.chunk')],
        'trackUrl' => fn (string $action, $track) => route('super-admin.radio.'.$action, $track),
        'schedules' => $schedules,
        'scheduleUrl' => fn (string $action, $schedule = null) => $action === 'store' ? route('super-admin.radio.schedule') : route('super-admin.radio.unschedule', $schedule),
    ])

    {{-- Church channels: each church fills its own; here a channel can be switched off --}}
    @if ($churchStations->isNotEmpty())
        <div class="ff-table-wrap mt-4">
            <div class="ff-table-toolbar"><strong>{{ __('Church channels') }}</strong> <span class="text-ff-muted small ms-2">{{ __('Each church fills its own songs radio and podcast channel from its admin area. You can switch one off.') }}</span></div>
            <div class="table-responsive">
                <table class="table ff-table align-middle">
                    <thead><tr><th>{{ __('Channel') }}</th><th>{{ __('Church') }}</th><th>{{ __('Tracks') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($churchStations as $s)
                            <tr>
                                <td class="fw-semibold">{{ $s->name }} <span class="ff-badge">{{ $s->kind === 'music' ? __('Songs radio') : __('Podcast channel') }}</span></td>
                                <td>{{ $s->church?->name }}</td>
                                <td>{{ $s->tracks_count }}</td>
                                <td>
                                    @if ($s->is_blocked)<span class="ff-badge ff-badge-danger">{{ __('Switched off by you') }}</span>
                                    @elseif ($s->is_enabled)<span class="ff-badge ff-badge-success">{{ __('On') }}</span>
                                    @else<span class="ff-badge">{{ __('Off (the church switched it off)') }}</span>@endif
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('super-admin.radio.block', $s) }}" class="d-inline" @if (! $s->is_blocked) data-ff-confirm="{{ __('Switch this channel off for everyone?') }}" @endif>@csrf @method('PUT')
                                        <button class="btn btn-sm {{ $s->is_blocked ? 'btn-ff-primary' : 'btn-ff-ghost text-danger' }}">{{ $s->is_blocked ? __('Switch back on') : __('Switch off') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.super-admin>
