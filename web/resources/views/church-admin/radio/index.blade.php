<x-layouts.church-admin :church="$church" :title="__('Radio channel')">
    <x-ff.page-header :title="__('Radio channel')" :subtitle="__('Teaching, sermons and podcasts that play round the clock, in a loop, for the members who follow your church. They find it in the app under Radio.')" />

    @if ($station->is_blocked)
        <div class="alert alert-danger"><i class="bi bi-slash-circle"></i> {{ __('This channel has been switched off by FaithFellows, so members cannot hear it. Please contact support.') }}</div>
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
        'urls' => ['settings' => route('church-admin.radio.settings', $church), 'chunk' => route('church-admin.radio.chunk', $church)],
        'trackUrl' => fn (string $action, $track) => route('church-admin.radio.'.$action, [$church, $track->id]),
        'schedules' => $schedules,
        'scheduleUrl' => fn (string $action, $schedule = null) => $action === 'store' ? route('church-admin.radio.schedule', $church) : route('church-admin.radio.unschedule', [$church, $schedule->id]),
    ])
</x-layouts.church-admin>
