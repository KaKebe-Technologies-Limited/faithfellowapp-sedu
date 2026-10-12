<div class="d-grid gap-4">
    @if ($church->hasSlider())
        @include('partials.home-slider', ['slides' => app(\App\Services\HomeSlider::class)->forUser(auth()->user() ?? new \App\Models\User, $church), 'sliderId' => 'ff-church-page-slider'])
    @endif

    <section class="ff-card">
        <h2 class="h5">{{ __('About :church', ['church' => $church->name]) }}</h2>
        @if ($church->tagline)<p class="fw-semibold text-ff-primary">{{ $church->tagline }}</p>@endif
        @if ($church->about)
            <div class="ff-prose" style="font-size: 1rem">{!! ff_safe_html($church->about) !!}</div>
        @elseif ($church->description)
            <p class="mb-0">{{ $church->description }}</p>
        @else
            <p class="text-ff-muted mb-0">{{ __('This church has not added a description yet.') }}</p>
        @endif
        <div class="d-flex flex-wrap gap-2 mt-3">
            @foreach ($church->languages ?? [] as $lang)
                <span class="ff-tag">{{ config('faithfellows.languages.'.$lang, $lang) }}</span>
            @endforeach
            @if ($church->founded_year)<span class="ff-badge">{{ __('Since :year', ['year' => $church->founded_year]) }}</span>@endif
        </div>
    </section>

    @if (request('join') && ! ($sections['subscription']?->isActive()) && auth()->check())
        <div class="ff-card bg-ff-gradient border-0 d-flex flex-column flex-md-row align-items-md-center gap-3">
            <div class="flex-grow-1"><h2 class="h5 text-white mb-1">{{ __('Welcome! Follow :church?', ['church' => $church->name]) }}</h2>
                <p class="small text-white mb-0" style="opacity:.9">{{ __('Get their sermons, live services and updates in your feed.') }}</p></div>
            <form method="POST" action="{{ route('churches.subscribe', $church) }}">@csrf
                <button class="btn btn-ff-white px-4">{{ __('Subscribe') }}</button>
            </form>
        </div>
    @endif

    @if (($sections['posts'] ?? collect())->isNotEmpty())
        <section class="d-grid gap-3">
            <h2 class="h5 mb-0">{{ __('Latest updates') }}</h2>
            @foreach ($sections['posts'] as $post)
                @include('member.feed._post', ['post' => $post->setRelation('church', $church)])
            @endforeach
        </section>
    @endif

    @isset($sections['sermons'])
        @if ($sections['sermons']->isNotEmpty())
            <section>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">{{ __('Latest sermons') }}</h2>
                    <a href="{{ route('churches.show', [$church, 'tab' => 'sermons']) }}" class="small">{{ __('View all') }}</a>
                </div>
                <div class="row g-3">
                    @foreach ($sections['sermons']->take(2) as $sermon)
                        <div class="col-md-6">@include('member.sermons._card', ['sermon' => $sermon])</div>
                    @endforeach
                </div>
            </section>
        @endif
    @endisset

    @isset($sections['events'])
        @if ($sections['events']->isNotEmpty())
            <section>
                <h2 class="h5 mb-3">{{ __('Upcoming events') }}</h2>
                <div class="d-grid gap-3">
                    @foreach ($sections['events']->take(3) as $event)
                        @include('member.events._row', ['event' => $event])
                    @endforeach
                </div>
            </section>
        @endif
    @endisset

    @isset($sections['campaigns'])
        @if ($sections['campaigns']->isNotEmpty())
            <section>
                <h2 class="h5 mb-3">{{ __('Active campaigns') }}</h2>
                <div class="row g-3">
                    @foreach ($sections['campaigns']->take(2) as $campaign)
                        <div class="col-md-6">@include('member.campaigns._card', ['campaign' => $campaign])</div>
                    @endforeach
                </div>
            </section>
        @endif
    @endisset

    @if ($church->branches->count() > 1)
        <section class="ff-card">
            <h2 class="h5 mb-3">{{ __('Branches') }}</h2>
            <div class="row g-3">
                @foreach ($church->branches as $branch)
                    <div class="col-md-6">
                        <div class="ff-card ff-card-gray ff-card-sm h-100">
                            <div class="fw-semibold text-ff-black">{{ $branch->name }} @if ($branch->is_main)<span class="ff-badge ff-badge-primary ms-1">{{ __('Main') }}</span>@endif</div>
                            <div class="small text-ff-muted">{{ $branch->address ?: $branch->city }}</div>
                            @if ($branch->leader_name)<div class="small">{{ __('Leader') }}: {{ $branch->leader_name }}</div>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
