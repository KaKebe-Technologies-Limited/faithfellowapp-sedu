<x-layouts.member :title="__('Live')">
    <x-ff.page-header :title="__('Live services')" :subtitle="__('Join your churches live — or listen in audio-only mode to save data.')" />

    @if ($live->isNotEmpty())
        <div class="row g-4 mb-5">
            @foreach ($live as $stream)
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('live.show', $stream) }}" class="ff-card ff-card-link ff-card-hover bg-ff-gradient border-0 d-block text-white">
                        <span class="ff-badge-live mb-3">{{ __('Live now') }}</span>
                        <div class="d-flex align-items-center gap-3">
                            <x-ff.church-avatar :church="$stream->church" class="ff-avatar-ring" />
                            <div class="min-w-0"><div class="fw-bold text-truncate">{{ $stream->title }}</div><div class="small" style="opacity:.9">{{ $stream->church->name }}</div></div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <h2 class="h6 text-uppercase text-ff-muted mb-3">{{ __('Coming up') }}</h2>
    @forelse ($upcoming as $stream)
        <a href="{{ route('live.show', $stream) }}" class="ff-card ff-card-sm ff-card-link d-flex align-items-center gap-3 mb-2" style="max-width: 860px">
            <div class="ff-date-tile"><div class="m">{{ $stream->scheduled_at->format('M') }}</div><div class="d">{{ $stream->scheduled_at->format('j') }}</div></div>
            <div class="min-w-0 flex-grow-1"><div class="fw-semibold text-ff-black">{{ $stream->title }}</div><div class="small text-ff-muted">{{ $stream->church->name }} · {{ $stream->scheduled_at->format('D g:i A') }}</div></div>
            <i class="bi bi-bell text-ff-primary" title="{{ __('You will be notified') }}"></i>
        </a>
    @empty
        <p class="text-ff-muted">{{ __('No upcoming live services scheduled.') }}</p>
    @endforelse

    @if ($recent->isNotEmpty())
        <h2 class="h6 text-uppercase text-ff-muted mt-5 mb-3">{{ __('Recent services') }}</h2>
        <div class="row g-3">
            @foreach ($recent as $stream)
                <div class="col-md-6 col-xl-3">
                    <a href="{{ $stream->sermon ? route('sermons.show', $stream->sermon) : route('live.show', $stream) }}" class="ff-card ff-card-sm ff-card-link h-100 d-block">
                        <div class="fw-semibold text-ff-black">{{ $stream->title }}</div>
                        <div class="small text-ff-muted">{{ $stream->church->name }} · {{ $stream->ended_at?->format('j M') }}</div>
                        <div class="small mt-1">{{ $stream->sermon ? __('▶ Watch recording') : __('Recording coming soon') }}</div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.member>
