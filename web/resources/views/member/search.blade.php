<x-layouts.member :title="__('Search')">
    <x-ff.page-header :title="$q ? __('Results for “:q”', ['q' => $q]) : __('Search')" />

    <form method="GET" class="mb-4" role="search" style="max-width: 640px">
        <div class="input-group">
            <input type="search" name="q" value="{{ $q }}" class="form-control form-control-lg" placeholder="{{ __('Search sermons, devotionals, churches, events…') }}" autofocus aria-label="{{ __('Search') }}">
            <button class="btn btn-ff-primary px-4">{{ __('Search') }}</button>
        </div>
    </form>

    @php($total = collect($results)->sum(fn ($c) => $c->count()))
    @if ($q && $total === 0)
        <x-ff.empty-state icon="bi-search" :title="__('Nothing found')" :message="__('Try different words, a preacher’s name or a Bible book.')" />
    @endif

    @if (($results['churches'] ?? collect())->isNotEmpty())
        <h2 class="h6 text-uppercase text-ff-muted mb-3">{{ __('Churches') }}</h2>
        <div class="row g-3 mb-4">
            @foreach ($results['churches'] as $church)
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('churches.show', $church) }}" class="ff-card ff-card-sm ff-card-link d-flex align-items-center gap-3">
                        <x-ff.church-avatar :church="$church" /> <span><span class="d-block fw-semibold text-ff-black">{{ $church->name }}</span><span class="small text-ff-muted">{{ $church->location }}</span></span>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    @if (($results['sermons'] ?? collect())->isNotEmpty())
        <h2 class="h6 text-uppercase text-ff-muted mb-3">{{ __('Sermons') }}</h2>
        <div class="row g-4 mb-4">
            @foreach ($results['sermons'] as $sermon)
                <div class="col-sm-6 col-lg-4 col-xxl-3">@include('member.sermons._card')</div>
            @endforeach
        </div>
    @endif

    @foreach (['devotionals' => ['devotionals.show', __('Devotionals'), 'bi-book'], 'announcements' => ['announcements.show', __('Announcements'), 'bi-megaphone'], 'events' => ['events.show', __('Events'), 'bi-calendar-event'], 'campaigns' => ['campaigns.show', __('Campaigns'), 'bi-flag']] as $key => [$route, $label, $icon])
        @if (($results[$key] ?? collect())->isNotEmpty())
            <h2 class="h6 text-uppercase text-ff-muted mb-3">{{ $label }}</h2>
            <div class="d-grid gap-2 mb-4" style="max-width: 860px">
                @foreach ($results[$key] as $item)
                    <a href="{{ route($route, $item) }}" class="ff-card ff-card-sm ff-card-link d-flex align-items-center gap-3">
                        <i class="bi {{ $icon }} fs-4 text-ff-primary"></i>
                        <span class="min-w-0"><span class="d-block fw-semibold text-ff-black text-truncate">{{ $item->title }}</span><span class="small text-ff-muted">{{ $item->church?->name }}</span></span>
                    </a>
                @endforeach
            </div>
        @endif
    @endforeach
</x-layouts.member>
