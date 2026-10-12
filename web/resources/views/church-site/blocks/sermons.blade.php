@php($sermons = ($sections['sermons'] ?? collect())->where('visibility', 'public')->take(3))
@if ($sermons->isNotEmpty())
    <section class="ff-section site-section" id="sermons">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
                <div><span class="ff-eyebrow site-accent">{{ __('Messages') }}</span><h2 class="mt-2 mb-0">{{ __('Latest sermons') }}</h2></div>
                <a href="{{ ff_platform_route('churches.show', [$church, 'tab' => 'sermons']) }}" class="btn site-btn">{{ __('All sermons') }}</a>
            </div>
            <div class="row g-4">
                @foreach ($sermons as $sermon)
                    <div class="col-md-4">
                        <a href="{{ ff_platform_route('sermons.show', $sermon) }}" class="ff-card ff-card-flush ff-card-hover ff-card-link h-100 d-block">
                            @if ($sermon->thumbnail_url)<img src="{{ $sermon->thumbnail_url }}" alt="" class="ff-cover" loading="lazy">@else<div class="ff-cover-placeholder" style="background: var(--site-accent)"><i class="bi bi-play-fill"></i></div>@endif
                            <div class="p-3"><div class="fw-bold text-ff-black">{{ $sermon->title }}</div><div class="small text-ff-muted">{{ $sermon->preacher }} · {{ $sermon->published_at?->format('j M Y') }}</div></div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
