@php($view = $filters['view'] ?? 'grid')
@php($layout = auth()->check() ? 'layouts.member' : 'layouts.public')
<x-dynamic-component :component="$layout" :title="__('Find a church')" :description="__('Search churches across Uganda by location, denomination, language and service times.')">
    @guest
        <section class="ff-page-hero pb-4">
            <div class="container">
                <span class="ff-eyebrow">{{ __('Church directory') }}</span>
                <h1 class="ff-display mt-3 mb-2" style="font-size: clamp(2rem, 4.5vw, 3rem)">{{ __('Find a church near you') }}</h1>
                <p class="ff-lead mb-0">{{ __('Browse churches on FaithFellows. Subscribe to follow their sermons, live services and events.') }}</p>
            </div>
        </section>
    @else
        <x-ff.page-header :title="__('Discover churches')" :subtitle="__('Subscribe to as many churches as you like — unsubscribe anytime.')" />
    @endguest

    <div @class(['container' => ! auth()->check(), 'pb-5' => ! auth()->check()])>
        <form method="GET" class="ff-card ff-card-sm mb-4" role="search">
            <div class="row g-2 align-items-end">
                <div class="col-lg-4">
                    <label for="q" class="form-label small">{{ __('Search') }}</label>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Church, pastor or town…') }}">
                </div>
                <div class="col-6 col-lg-2">
                    <label for="city" class="form-label small">{{ __('Location') }}</label>
                    <select id="city" name="city" class="form-select">
                        <option value="">{{ __('Anywhere') }}</option>
                        @foreach ($cities as $city)<option @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="denomination" class="form-label small">{{ __('Denomination') }}</label>
                    <select id="denomination" name="denomination" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($denominations as $d)<option @selected(($filters['denomination'] ?? '') === $d)>{{ $d }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-1">
                    <label for="language" class="form-label small">{{ __('Language') }}</label>
                    <select id="language" name="language" class="form-select">
                        <option value="">{{ __('Any') }}</option>
                        @foreach (config('faithfellows.languages') as $code => $label)<option value="{{ $code }}" @selected(($filters['language'] ?? '') === $code)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-1">
                    <label for="day" class="form-label small">{{ __('Service day') }}</label>
                    <select id="day" name="day" class="form-select">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($days as $i => $day)<option value="{{ $i }}" @selected(isset($filters['day']) && (int) $filters['day'] === $i)>{{ __($day) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <input type="hidden" name="view" value="{{ $view }}">
                    <button class="btn btn-ff-primary flex-grow-1">{{ __('Search') }}</button>
                    @if (array_filter($filters))
                        <a href="{{ route('churches.index') }}" class="btn btn-ff-ghost" aria-label="{{ __('Clear filters') }}"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <span class="small text-ff-muted">{{ trans_choice(':count church found|:count churches found', $churches->total(), ['count' => $churches->total()]) }}</span>
            <div class="ff-pills">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'grid', 'page' => null]) }}" @class(['ff-pill', 'active' => $view === 'grid'])><i class="bi bi-grid"></i> {{ __('Grid') }}</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'map', 'page' => null]) }}" @class(['ff-pill', 'active' => $view === 'map'])><i class="bi bi-map"></i> {{ __('Map') }}</a>
            </div>
        </div>

        @if ($churches->isEmpty())
            <x-ff.empty-state icon="bi-buildings" :title="__('No churches match your search')" :message="__('Try a different town or clear the filters.')">
                <a href="{{ route('church.apply') }}" class="btn btn-ff-outline">{{ __('Is your church missing? Register it') }}</a>
            </x-ff.empty-state>
        @elseif ($view === 'map')
            <div class="ff-card ff-card-flush">
                @php($mapPoints = $churches->map(fn ($c) => ['lat' => $c->lat, 'lng' => $c->lng, 'label' => $c->name, 'sub' => $c->location, 'url' => route('churches.show', $c)])->values())
                <div style="height: 560px" data-ff-map="{{ json_encode($mapPoints) }}"></div>
            </div>
        @else
            <div class="row g-4">
                @foreach ($churches as $church)
                    <div class="col-sm-6 col-xl-4">@include('public.churches._card', ['church' => $church])</div>
                @endforeach
            </div>
            <div class="mt-4 d-flex justify-content-center">{{ $churches->links() }}</div>
        @endif
    </div>
</x-dynamic-component>
