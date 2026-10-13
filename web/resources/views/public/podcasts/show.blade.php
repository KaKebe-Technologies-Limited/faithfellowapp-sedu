<x-layouts.public :title="$podcast->name" :description="\Illuminate\Support\Str::limit($podcast->summary, 160)" :image="$podcast->coverUrl()">
    <section class="ff-page-hero">
        <div class="container">
            <nav class="small mb-3" aria-label="{{ __('Breadcrumb') }}"><a href="{{ route('podcasts.listen') }}">{{ __('Podcasts') }}</a> <span class="text-ff-muted">/</span> <span class="text-ff-muted">{{ $podcast->name }}</span></nav>
            <div class="d-flex flex-wrap gap-4 align-items-center">
                <img src="{{ $podcast->coverUrl() }}" alt="" class="rounded-4" style="width:180px;height:180px;object-fit:cover">
                <div class="flex-grow-1" style="min-width:260px;max-width:680px">
                    <span class="ff-eyebrow">{{ $podcast->categoryLabel() }}</span>
                    <h1 class="ff-display mt-2 mb-1">{{ $podcast->name }}</h1>
                    <p class="text-ff-muted mb-2">{{ __('Presented by :name', ['name' => $podcast->host_name]) }} · {{ trans_choice(':n episode|:n episodes', $podcast->episodes_count, ['n' => $podcast->episodes_count]) }}</p>
                    <p class="ff-lead mb-3">{{ $podcast->summary }}</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('podcasts.listen.show', $podcast->slug) }}#episodes" class="btn btn-ff-primary"><i class="bi bi-play-fill"></i> {{ __('Listen now') }}</a>
                        @if ($podcast->website_url)<a href="{{ $podcast->website_url }}" class="btn btn-ff-soft" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> {{ __('Podcast website') }}</a>@endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ff-section pt-4" id="episodes">
        <div class="container" style="max-width: 860px">
            @if ($podcast->description && $podcast->description !== $podcast->summary)
                <p class="text-ff-muted" style="white-space:pre-line">{{ $podcast->description }}</p>
            @endif
            <h2 class="h4 mb-3">{{ __('Episodes') }}</h2>
            @forelse ($episodes as $episode)
                <article class="ff-card mb-3">
                    <div class="d-flex justify-content-between gap-3 flex-wrap">
                        <h3 class="h6 mb-1">{{ $episode->title }}</h3>
                        <div class="small text-ff-muted text-nowrap">{{ $episode->published_at?->format('j M Y') }}@if ($episode->durationLabel()) · {{ $episode->durationLabel() }}@endif</div>
                    </div>
                    @if ($episode->summary)<p class="small text-ff-muted mb-2" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($episode->summary, 400) }}</p>@endif
                    <audio controls preload="none" src="{{ $episode->audio_url }}" class="w-100" aria-label="{{ $episode->title }}"></audio>
                </article>
            @empty
                <x-ff.empty-state icon="bi-collection-play" :title="__('No episodes yet')" :message="__('Check back soon.')" />
            @endforelse

            <div class="ff-card ff-card-mist mt-4">
                <strong>{{ __('Listen while you read, take notes and chat') }}</strong>
                <p class="small mb-0">{{ __('Follow this podcast in the FaithFellows app: the episode keeps playing while you read the Bible, write notes or talk with your group.') }}</p>
            </div>
        </div>
    </section>
</x-layouts.public>
