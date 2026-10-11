@if ($church->serviceTimes->isNotEmpty())
    <div class="ff-card">
        <h2 class="h6 mb-3"><i class="bi bi-clock text-ff-primary"></i> {{ __('Service times') }}</h2>
        <ul class="list-unstyled mb-0 d-grid gap-2">
            @foreach ($church->serviceTimes as $service)
                <li class="d-flex justify-content-between gap-2 small">
                    <span><strong class="text-ff-black">{{ $service->name }}</strong><br><span class="text-ff-muted">{{ $service->day_name }}@if ($service->branch) · {{ $service->branch->name }}@endif</span></span>
                    <span class="text-nowrap fw-semibold">{{ $service->time_range }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

@isset($sections['liveNow'])
    @if ($sections['liveNow'])
        <a href="{{ route('live.show', $sections['liveNow']) }}" class="ff-card bg-ff-gradient border-0 text-white ff-card-link">
            <span class="ff-badge-live mb-2">{{ __('Live now') }}</span>
            <div class="fw-bold">{{ $sections['liveNow']->title }}</div>
            <div class="small" style="opacity:.85">{{ __('Join the service') }} <i class="bi bi-arrow-right"></i></div>
        </a>
    @endif
@endisset

<div class="ff-card">
    <h2 class="h6 mb-3">{{ __('Share this church') }}</h2>
    <x-ff.share :url="route('churches.show', $church)" :title="$church->name" />
    <hr>
    <a href="{{ ff_church_url($church) }}" class="small" target="_blank" rel="noopener"><i class="bi bi-globe"></i> {{ Str::after(ff_church_url($church), '://') }}</a>
</div>

@if ($church->socialLinks->isNotEmpty())
    <div class="ff-card">
        <h2 class="h6 mb-3">{{ __('Follow') }}</h2>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($church->socialLinks as $link)
                <a href="{{ $link->url }}" class="btn btn-icon btn-ff-soft" target="_blank" rel="noopener" aria-label="{{ ucfirst($link->platform) }}"><i class="bi {{ $link->icon }}"></i></a>
            @endforeach
        </div>
    </div>
@endif
