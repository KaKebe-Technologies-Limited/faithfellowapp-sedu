<x-layouts.public :title="__('Blog & resources')" :description="__('Articles, guides and announcements for digital ministry.')">
    <section class="ff-page-hero">
        <div class="container">
            <span class="ff-eyebrow">{{ __('Blog & resources') }}</span>
            <h1 class="ff-display mt-3 mb-3">{{ __('Ideas for digital ministry') }}</h1>
            <p class="ff-lead mb-4" style="max-width: 640px">{{ __('Guides, stories and platform announcements to help your church grow online.') }}</p>
            <form class="d-flex gap-2 flex-wrap" method="GET" role="search">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" style="max-width: 360px" placeholder="{{ __('Search articles…') }}" aria-label="{{ __('Search articles') }}">
                <button class="btn btn-ff-primary px-4">{{ __('Search') }}</button>
            </form>
        </div>
    </section>

    <section class="ff-section pt-4">
        <div class="container">
            @if ($categories->isNotEmpty())
                <div class="ff-pills mb-4">
                    <a href="{{ route('blog.index') }}" @class(['ff-pill', 'active' => ! request('category')])>{{ __('All') }}</a>
                    @foreach ($categories as $cat)
                        <a href="{{ route('blog.index', ['category' => $cat]) }}" @class(['ff-pill', 'active' => request('category') === $cat])>{{ $cat }}</a>
                    @endforeach
                </div>
            @endif

            @if ($posts->isEmpty())
                <x-ff.empty-state icon="bi-journal" :title="__('No articles found')" :message="__('Try another search or category.')" />
            @else
                <div class="row g-4">
                    @foreach ($posts as $post)
                        <div class="col-md-6 col-lg-4">@include('public.blog._card', ['post' => $post])</div>
                    @endforeach
                </div>
                <div class="mt-5 d-flex justify-content-center">{{ $posts->links() }}</div>
            @endif
        </div>
    </section>
</x-layouts.public>
