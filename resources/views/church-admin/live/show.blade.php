<x-layouts.church-admin :church="$church" :title="__('Control room')">
    <x-ff.page-header :title="$stream->title" :subtitle="__('Control room')"
        :breadcrumbs="[__('Live streams') => route('church-admin.live.index', $church), $stream->title => null]">
        <a href="{{ route('live.show', $stream) }}" target="_blank" class="btn btn-ff-ghost"><i class="bi bi-eye"></i> {{ __('Viewer page') }}</a>
        <a href="{{ route('church-admin.live.edit', [$church, $stream]) }}" class="btn btn-ff-outline">{{ __('Edit') }}</a>
    </x-ff.page-header>

    <div class="row g-4">
        <div class="col-lg-7 d-grid gap-4 align-content-start">
            <div class="ff-card" x-data="{ status: '{{ $stream->status }}', viewers: {{ $viewers }}, peak: {{ $stream->peak_viewers }},
                init() { setInterval(() => ff.http('{{ route('church-admin.live.stats', [$church, $stream]) }}').then(d => { this.status = d.status; this.viewers = d.viewers; this.peak = d.peak; }).catch(() => {}), 15000); } }">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <template x-if="status === 'live'"><span class="ff-badge-live">{{ __('Live') }}</span></template>
                    <template x-if="status !== 'live'"><span class="ff-badge" x-text="status"></span></template>
                    <span class="small"><i class="bi bi-eye"></i> <strong x-text="viewers"></strong> {{ __('watching') }} · {{ __('peak') }} <strong x-text="peak"></strong></span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if (in_array($stream->status, ['scheduled', 'ended'], true))
                        <form method="POST" action="{{ route('church-admin.live.start', [$church, $stream]) }}">@csrf
                            <button class="btn btn-danger rounded-pill px-4"><i class="bi bi-broadcast"></i> {{ __('Go live now') }}</button>
                        </form>
                    @endif
                    @if ($stream->isLive())
                        <form method="POST" action="{{ route('church-admin.live.stop', [$church, $stream]) }}" data-ff-confirm="{{ __('End the live stream?') }}">@csrf
                            <button class="btn btn-dark rounded-pill px-4"><i class="bi bi-stop-circle"></i> {{ __('End stream') }}</button>
                        </form>
                    @endif
                </div>
                <p class="small text-ff-muted mt-3 mb-0">{{ __('With FaithFellows streaming, starting OBS/Larix goes live automatically and members are notified (push, email, SMS per their settings).') }}</p>
            </div>

            @if ($stream->status === 'ended')
                <form method="POST" action="{{ route('church-admin.live.archive', [$church, $stream]) }}" class="ff-card ff-card-mist">@csrf
                    <h2 class="h6">{{ __('Archive as sermon') }}</h2>
                    <p class="small">{{ __('Publish the recording to your sermon library.') }}</p>
                    <x-ff.input name="recording_url" type="url" :label="__('Recording link')" :value="$stream->recording_url" />
                    <div class="row">
                        <div class="col-md-6"><x-ff.input name="preacher" :label="__('Preacher')" /></div>
                        <div class="col-md-6"><x-ff.input name="scripture" :label="__('Scripture')" /></div>
                    </div>
                    <button class="btn btn-ff-primary">{{ __('Publish sermon') }}</button>
                </form>
            @elseif ($stream->sermon)
                <div class="ff-card ff-card-mist"><i class="bi bi-check-circle text-success"></i> {{ __('Archived as') }} <a href="{{ route('sermons.show', $stream->sermon) }}">{{ $stream->sermon->title }}</a></div>
            @endif
        </div>

        <div class="col-lg-5 d-grid gap-4 align-content-start">
            @if ($stream->source_type === 'rtmp')
                <div class="ff-card" x-data="{ show: false }">
                    <h2 class="h6"><i class="bi bi-key"></i> {{ __('Streaming software settings') }}</h2>
                    <label class="form-label small mb-1">{{ __('Server (RTMP URL)') }}</label>
                    <div class="input-group input-group-sm mb-2">
                        <input class="form-control" value="{{ $ingest }}" readonly aria-label="{{ __('Server') }}">
                        <button type="button" class="btn btn-ff-soft" data-ff-copy="{{ $ingest }}"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <label class="form-label small mb-1">{{ __('Stream key') }}</label>
                    <div class="input-group input-group-sm mb-2">
                        <input class="form-control" :type="show ? 'text' : 'password'" value="{{ $stream->stream_key }}" readonly aria-label="{{ __('Stream key') }}">
                        <button type="button" class="btn btn-ff-soft" @click="show = !show"><i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i></button>
                        <button type="button" class="btn btn-ff-soft" data-ff-copy="{{ $stream->stream_key }}"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <form method="POST" action="{{ route('church-admin.live.key', [$church, $stream]) }}" data-ff-confirm="{{ __('Generate a new key? The old one stops working.') }}">@csrf
                        <button class="btn btn-sm btn-ff-ghost px-0"><i class="bi bi-arrow-repeat"></i> {{ __('Reset key') }}</button>
                    </form>
                    <hr>
                    <ul class="small text-ff-muted ps-3 mb-0">
                        <li>{{ __('OBS: Settings → Stream → Custom, paste server & key.') }}</li>
                        <li>{{ __('Phone: Larix Broadcaster → Connections → rtmp URL + "/" + key.') }}</li>
                        <li>{{ __('Recommended: 720p, 2,500 kbps video, 64 kbps audio.') }}</li>
                    </ul>
                </div>
            @else
                <div class="ff-card"><h2 class="h6">{{ __('Source') }}</h2><a href="{{ $stream->external_url }}" target="_blank" class="small text-break">{{ $stream->external_url }}</a></div>
            @endif
            <div class="ff-card">
                <h2 class="h6">{{ __('Share') }}</h2>
                <x-ff.share :url="route('live.show', $stream)" :title="$stream->title.' — '.$church->name" />
            </div>
            @unless ($stream->isLive())
                <form method="POST" action="{{ route('church-admin.live.destroy', [$church, $stream]) }}" data-ff-confirm="{{ __('Delete this stream?') }}">@csrf @method('DELETE')
                    <button class="btn btn-ff-ghost text-danger w-100">{{ __('Delete stream') }}</button>
                </form>
            @endunless
        </div>
    </div>
</x-layouts.church-admin>
