<article class="ff-card ff-feed-item">
    <div class="ff-feed-meta">
        <x-ff.church-avatar :church="$sermon->church" size="sm" />
        <div class="min-w-0">
            <a href="{{ route('churches.show', $sermon->church) }}" class="fw-semibold text-ff-black">{{ $sermon->church->name }}</a>
            <div class="small-xs text-ff-muted">{{ $sermon->published_at->diffForHumans() }}</div>
        </div>
        <span class="ff-feed-type ff-tag"><i class="bi bi-mic"></i> {{ __('Sermon') }}</span>
    </div>
    <a href="{{ route('sermons.show', $sermon) }}" class="d-flex gap-3 align-items-center ff-card-link">
        @if ($sermon->thumbnail_url)
            <img src="{{ $sermon->thumbnail_url }}" alt="" class="rounded-3 ff-cover" style="width:132px;aspect-ratio:16/9" loading="lazy">
        @else
            <span class="ff-cover-placeholder rounded-3" style="width:132px;font-size:1.6rem"><i class="bi bi-play-fill"></i></span>
        @endif
        <span class="min-w-0">
            <span class="d-block fw-bold text-ff-black">{{ $sermon->title }}</span>
            <span class="d-block small text-ff-muted">{{ collect([$sermon->preacher, $sermon->scripture, $sermon->duration_label])->filter()->implode(' · ') }}</span>
            @if ($sermon->series)<span class="ff-tag mt-1">{{ $sermon->series->title }}</span>@endif
        </span>
    </a>
    @include('partials.engagement-bar', ['model' => $sermon, 'type' => 'sermon'])
</article>
