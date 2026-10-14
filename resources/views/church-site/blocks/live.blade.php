@if ($sections['liveNow'] ?? null)
    <section class="py-4" style="background: var(--ff-danger)">
        <div class="container d-flex flex-column flex-md-row align-items-md-center gap-3 text-white">
            <span class="ff-badge-live" style="background:#fff;color:var(--ff-danger)">{{ __('Live now') }}</span>
            <div class="fw-bold fs-5 flex-grow-1">{{ $sections['liveNow']->title }}</div>
            <a href="{{ ff_platform_route('live.show', $sections['liveNow']) }}" class="btn btn-ff-white">{{ __('Watch / listen') }}</a>
        </div>
    </section>
@elseif ($sections['nextLive'] ?? null)
    <section class="py-4 bg-ff-mist">
        <div class="container d-flex flex-column flex-md-row align-items-md-center gap-3">
            <i class="bi bi-broadcast fs-3 site-accent"></i>
            <div class="flex-grow-1"><div class="fw-bold text-ff-black">{{ __('Next live service') }}: {{ $sections['nextLive']->title }}</div><div class="small text-ff-muted">{{ $sections['nextLive']->scheduled_at->format('l j F, g:i A') }}</div></div>
            <a href="{{ ff_platform_route('live.show', $sections['nextLive']) }}" class="btn site-btn">{{ __('Set a reminder') }}</a>
        </div>
    </section>
@endif
