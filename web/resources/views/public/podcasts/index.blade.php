<x-layouts.public :title="__('Podcasts')" :description="__('Faith-based podcasts to listen to anywhere, made by FaithFellows members.')">
    <section class="ff-page-hero">
        <div class="container">
            <span class="ff-eyebrow">{{ __('Podcasts') }}</span>
            <h1 class="ff-display mt-3 mb-3">{{ __('Faith to listen to') }}</h1>
            <p class="ff-lead mb-4" style="max-width: 640px">{{ __('Faith-based podcasts made by our members. Listen here, or follow them in the FaithFellows app and listen while you read, take notes and chat.') }}</p>
            <form class="d-flex gap-2 flex-wrap" method="GET" role="search">
                @if ($category)<input type="hidden" name="category" value="{{ $category }}">@endif
                <input type="search" name="q" value="{{ $q }}" class="form-control" style="max-width: 360px" placeholder="{{ __('Search podcasts…') }}" aria-label="{{ __('Search podcasts') }}">
                <button class="btn btn-ff-primary px-4">{{ __('Search') }}</button>
            </form>
        </div>
    </section>

    <section class="ff-section pt-4">
        <div class="container">
            <div class="ff-pills mb-4">
                <a href="{{ route('podcasts.listen', array_filter(['q' => $q])) }}" @class(['ff-pill', 'active' => ! $category])>{{ __('All') }}</a>
                @foreach (\App\Models\Podcast::CATEGORIES as $key => $label)
                    <a href="{{ route('podcasts.listen', array_filter(['q' => $q, 'category' => $key])) }}" @class(['ff-pill', 'active' => $category === $key])>{{ __($label) }}</a>
                @endforeach
            </div>

            @if ($podcasts->isEmpty())
                <x-ff.empty-state icon="bi-mic" :title="__('No podcasts found')" :message="__('Try another search or category.')" />
            @else
                <div class="row g-4">
                    @foreach ($podcasts as $podcast)
                        <div class="col-md-6 col-lg-4">
                            <a href="{{ route('podcasts.listen.show', $podcast->slug) }}" class="ff-card d-flex gap-3 text-decoration-none h-100">
                                <img src="{{ $podcast->coverUrl() }}" alt="" class="rounded-3 flex-shrink-0" style="width:96px;height:96px;object-fit:cover" loading="lazy">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-ff-black">{{ $podcast->name }}</div>
                                    <div class="small text-ff-muted">{{ $podcast->host_name }} · {{ $podcast->categoryLabel() }}</div>
                                    <p class="small text-ff-muted mb-1 mt-1">{{ \Illuminate\Support\Str::limit($podcast->summary, 110) }}</p>
                                    <div class="small text-ff-primary">{{ trans_choice(':n episode|:n episodes', $podcast->episodes_count, ['n' => $podcast->episodes_count]) }}</div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">{{ $podcasts->links() }}</div>
            @endif
        </div>
    </section>
</x-layouts.public>
