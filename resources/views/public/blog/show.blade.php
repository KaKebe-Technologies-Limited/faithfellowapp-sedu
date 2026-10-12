<x-layouts.public :title="$post->title" :description="$post->excerpt" :image="$post->cover_url">
    @push('head')
        <script type="application/ld+json">{!! json_encode(array_filter([
            '@@context' => 'https://schema.org', '@@type' => 'BlogPosting', 'headline' => $post->title, 'description' => $post->excerpt,
            'image' => $post->cover_url, 'datePublished' => $post->published_at?->toIso8601String(), 'dateModified' => $post->updated_at?->toIso8601String(),
            'author' => ['@@type' => 'Organization', 'name' => config('faithfellows.company.name')],
            'publisher' => ['@@type' => 'Organization', 'name' => config('faithfellows.brand.name'), 'logo' => ['@@type' => 'ImageObject', 'url' => asset('images/icon-512.png')]],
            'mainEntityOfPage' => route('blog.show', $post),
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush
    <article>
        <header class="ff-page-hero">
            <div class="container" style="max-width: 820px">
                <a href="{{ route('blog.index') }}" class="small"><i class="bi bi-arrow-left"></i> {{ __('All articles') }}</a>
                <div class="d-flex gap-2 align-items-center small text-ff-muted mt-4 mb-2">
                    <span class="ff-tag">{{ $post->category }}</span>
                    <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('j F Y') }}</time>
                    <span>· {{ $post->reading_minutes }} {{ __('min read') }}</span>
                </div>
                <h1 class="ff-display" style="font-size: clamp(2rem, 4.5vw, 3rem)">{{ $post->title }}</h1>
                @if ($post->excerpt)<p class="ff-lead">{{ $post->excerpt }}</p>@endif
                @if ($post->author)
                    <div class="d-flex align-items-center gap-2 mt-3">
                        <x-ff.avatar :user="$post->author" size="sm" />
                        <span class="small fw-semibold text-ff-black">{{ $post->author->name }}</span>
                    </div>
                @endif
            </div>
        </header>
        <div class="container pb-5" style="max-width: 820px">
            @if ($post->cover_url)
                <img src="{{ $post->cover_url }}" alt="" class="ff-cover ff-rounded-xl mb-5">
            @endif
            <div class="ff-prose">{!! ff_safe_html($post->body) !!}</div>
            <hr class="my-5">
            <x-ff.share :url="route('blog.show', $post)" :title="$post->title" />
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="ff-section bg-ff-gray">
            <div class="container">
                <h2 class="h4 mb-4">{{ __('Related articles') }}</h2>
                <div class="row g-4">
                    @foreach ($related as $r)
                        <div class="col-md-4">@include('public.blog._card', ['post' => $r])</div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.public>
