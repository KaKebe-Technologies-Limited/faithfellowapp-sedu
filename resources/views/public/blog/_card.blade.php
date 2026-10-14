<article class="ff-card ff-card-flush ff-card-hover h-100 d-flex flex-column">
    @if ($post->cover_url)
        <img src="{{ $post->cover_url }}" alt="" class="ff-cover" loading="lazy">
    @else
        <div class="ff-cover-placeholder"><i class="bi bi-journal-richtext"></i></div>
    @endif
    <div class="p-4 d-flex flex-column flex-grow-1">
        <div class="d-flex gap-2 align-items-center small text-ff-muted mb-2">
            <span class="ff-tag">{{ $post->category }}</span>
            <span>{{ $post->published_at?->format('j M Y') }}</span>
            <span>· {{ $post->reading_minutes }} {{ __('min read') }}</span>
        </div>
        <h3 class="h5"><a href="{{ route('blog.show', $post) }}" class="text-ff-black stretched-link">{{ $post->title }}</a></h3>
        <p class="small text-ff-muted mb-0">{{ $post->excerpt }}</p>
    </div>
</article>
