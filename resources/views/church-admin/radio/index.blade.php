@php
    $music = $station->kind === 'music';
    $routes = $music ? 'church-admin.songs.' : 'church-admin.radio.';
    $heading = $music ? __('Church radio (songs)') : __('Podcast channel');
@endphp
<x-layouts.church-admin :church="$church" :title="$heading">
    <x-ff.page-header
        :title="$heading"
        :subtitle="$music
            ? __('Your own radio. The songs you add play round the clock, in a loop, on the app’s Home screen for the members who follow your church. Until you add a song, the FaithFellows Gospel radio plays for them instead.')
            : __('Teaching, sermons and podcasts that play round the clock, in a loop, for the members who follow your church. They find it in the app under Podcasts, with the FaithFellows Podcast radio.')" />

    <ul class="nav nav-pills gap-2 mb-4">
        <li class="nav-item"><a href="{{ route('church-admin.songs.index', $church) }}" @class(['nav-link', 'active' => $music])><i class="bi bi-music-note-beamed"></i> {{ __('Radio (songs)') }}</a></li>
        <li class="nav-item"><a href="{{ route('church-admin.radio.index', $church) }}" @class(['nav-link', 'active' => ! $music])><i class="bi bi-mic"></i> {{ __('Podcast channel') }}</a></li>
    </ul>

    @if ($station->is_blocked)
        <div class="alert alert-danger"><i class="bi bi-slash-circle"></i> {{ __('This channel has been switched off by FaithFellows, so members cannot hear it. Please contact support.') }}</div>
    @endif

    @if ($music)
        <div class="alert alert-info small"><i class="bi bi-broadcast"></i>
            @if ($tracks->where('is_active', true)->isNotEmpty())
                {{ __('Your radio is on air 24 hours a day with :count :songs (one round lasts :length). Members who follow your church hear it first on Home. FaithFellows may interrupt every radio now and then with a message for everyone.', ['count' => $tracks->where('is_active', true)->count(), 'songs' => __('songs'), 'length' => gmdate($loopSeconds >= 3600 ? 'G\h i\m' : 'i\m s\s', $loopSeconds)]) }}
            @else
                {{ __('Your radio has no songs yet, so your members hear the FaithFellows Gospel radio. Add your first song below and your own radio takes its place.') }}
            @endif
        </div>
    @endif

    <div class="ff-card mb-4">
        <div class="d-flex justify-content-between small mb-1"><span>{{ __('Your storage: :used MB used', ['used' => $usedMb]) }}</span><span class="text-ff-muted">{{ number_format($storageLimitMb) }} MB</span></div>
        <x-ff.progress :value="$percent" :label="__('Storage used')" />
        <p class="small text-ff-muted mt-2 mb-0">{{ __('Audio you add here uses your church’s storage, like the media library.') }}</p>
    </div>

    @include('partials.radio-manager', [
        'station' => $station,
        'tracks' => $tracks,
        'now' => $now,
        'name' => $name,
        'enabled' => $enabled,
        'loopSeconds' => $loopSeconds,
        'limitMb' => $limitMb,
        'stationField' => null,
        'urls' => ['settings' => route($routes.'settings', $church), 'chunk' => route($routes.'chunk', $church)],
        'trackUrl' => fn (string $action, $track) => route($routes.$action, [$church, $track->id]),
        'schedules' => $schedules,
        'scheduleUrl' => fn (string $action, $schedule = null) => $action === 'store' ? route($routes.'schedule', $church) : route($routes.'unschedule', [$church, $schedule->id]),
    ])
</x-layouts.church-admin>
