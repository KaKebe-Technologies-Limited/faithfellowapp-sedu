<x-layouts.super-admin :title="__('Radio')">
    <x-ff.page-header :title="__('Radio')" :subtitle="__('Channels that play round the clock in the app, in a loop. Everyone hears the same thing at the same moment, like a radio station.')" />

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
    ])

    {{-- Church channels: each church fills its own; here a channel can be switched off --}}
    @if ($churchStations->isNotEmpty())
        <div class="ff-table-wrap mt-4">
            <div class="ff-table-toolbar"><strong>{{ __('Church channels') }}</strong> <span class="text-ff-muted small ms-2">{{ __('Each church fills its own channel from its admin area. You can switch one off.') }}</span></div>
            <div class="table-responsive">
                <table class="table ff-table align-middle">
                    <thead><tr><th>{{ __('Channel') }}</th><th>{{ __('Church') }}</th><th>{{ __('Episodes') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($churchStations as $s)
                            <tr>
                                <td class="fw-semibold">{{ $s->name }}</td>
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
