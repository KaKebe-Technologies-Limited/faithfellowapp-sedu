{{-- Feed card for a church post. Expects $post (with church, author, media). --}}
<article class="ff-card ff-feed-item">
    <div class="ff-feed-meta">
        <x-ff.church-avatar :church="$post->church" size="sm" />
        <div class="min-w-0">
            <a href="{{ route('churches.show', $post->church) }}" class="fw-semibold text-ff-black">{{ $post->church->name }}</a>
            <div class="small-xs text-ff-muted">
                {{ $post->published_at->diffForHumans() }}
                @if ($post->visibility === 'members')· <i class="bi bi-people" title="{{ __('Members only') }}"></i>@endif
                @if ($post->is_pinned)· <i class="bi bi-pin-angle-fill text-ff-primary" title="{{ __('Pinned') }}"></i>@endif
            </div>
        </div>
        <span class="ff-feed-type ff-tag">{{ __('Update') }}</span>
        @include('partials.report-button', ['type' => 'post', 'id' => $post->id])
    </div>
    <div class="text-ff-black" style="white-space: pre-line">{{ $post->body }}</div>
    @if ($post->media->isNotEmpty())
        <div @class(['row g-2', 'ff-decorative' => false])>
            @foreach ($post->media->take(4) as $image)
                <div @class(['col-12' => $post->media->count() === 1, 'col-6' => $post->media->count() > 1])>
                    <img src="{{ $image->getUrl('feed') }}" alt="" loading="lazy" class="w-100 rounded-3 ff-cover" style="aspect-ratio: {{ $post->media->count() === 1 ? '16/10' : '1/1' }}">
                </div>
            @endforeach
        </div>
    @endif
    @includeIf('partials.engagement-bar', ['model' => $post, 'type' => 'post'])
</article>
