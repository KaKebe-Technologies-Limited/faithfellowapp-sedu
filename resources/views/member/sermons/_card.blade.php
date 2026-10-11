{{-- Sermon card. Expects $sermon with church + media loaded. --}}
<article class="ff-card ff-card-flush ff-card-hover h-100 d-flex flex-column">
    <div class="position-relative">
        @if ($sermon->thumbnail_url)
            <img src="{{ $sermon->thumbnail_url }}" alt="" class="ff-cover" loading="lazy">
        @else
            <div class="ff-cover-placeholder"><i class="bi {{ $sermon->video_url || $sermon->video_file_src ? 'bi-play-btn' : 'bi-mic' }}"></i></div>
        @endif
        @if ($sermon->duration_label)
            <span class="position-absolute bottom-0 end-0 m-2 ff-badge ff-badge-dark">{{ $sermon->duration_label }}</span>
        @endif
        @if ($sermon->visibility === 'members')
            <span class="position-absolute top-0 start-0 m-2 ff-badge" title="{{ __('Members only') }}"><i class="bi bi-people"></i></span>
        @endif
    </div>
    <div class="p-3 d-flex flex-column flex-grow-1">
        <h3 class="h6 mb-1"><a href="{{ route('sermons.show', $sermon) }}" class="text-ff-black stretched-link">{{ $sermon->title }}</a></h3>
        <div class="small text-ff-muted">{{ collect([$sermon->preacher, $sermon->scripture])->filter()->implode(' · ') }}</div>
        <div class="mt-auto pt-2 d-flex align-items-center gap-2 small-xs text-ff-muted">
            <x-ff.church-avatar :church="$sermon->church" size="xs" />
            <span class="text-truncate">{{ $sermon->church->name }}</span>
            <span class="ms-auto text-nowrap">{{ $sermon->published_at?->format('j M') }}</span>
        </div>
    </div>
</article>
