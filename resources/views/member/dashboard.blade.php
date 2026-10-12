<x-layouts.member :title="__('Home')">
    <div class="row g-4">
        <div class="col-xl-8">
            @if (! $filterChurch && $slides->isNotEmpty())
                <div class="d-flex align-items-end justify-content-between gap-3 mb-3">
                    <div>
                        <span class="small fw-semibold text-ff-muted text-uppercase">{{ now()->format('l, j F') }}</span>
                        <h1 class="h3 mb-0">{{ __('Good :time, :name', ['time' => now()->hour < 12 ? __('morning') : (now()->hour < 17 ? __('afternoon') : __('evening')), 'name' => $user->first_name]) }}</h1>
                    </div>
                    <a href="{{ route('bible.index') }}" class="btn btn-ff-soft btn-sm d-none d-sm-inline-flex"><i class="bi bi-book-half"></i> {{ __('Open the Bible') }}</a>
                </div>
                @include('partials.home-slider', ['slides' => $slides, 'sliderId' => 'ff-home-slider'])
            @elseif (! $filterChurch)
                <div class="ff-card bg-ff-gradient border-0 mb-4 p-4 position-relative overflow-hidden">
                    <x-logo-mark :size="180" class="position-absolute ff-decorative" style="right:-30px;bottom:-50px;opacity:.12;color:#fff" />
                    <span class="small fw-semibold text-white-50 text-uppercase">{{ now()->format('l, j F') }}</span>
                    <h1 class="h3 text-white mt-1 mb-1">{{ __('Good :time, :name', ['time' => now()->hour < 12 ? __('morning') : (now()->hour < 17 ? __('afternoon') : __('evening')), 'name' => $user->first_name]) }}</h1>
                    <p class="text-white mb-0" style="opacity:.9">{{ __('Here is what is happening in your churches.') }}</p>
                </div>
            @else
                <div class="d-flex align-items-center gap-3 mb-4">
                    <x-ff.church-avatar :church="$filterChurch" size="lg" />
                    <div class="flex-grow-1 min-w-0">
                        <h1 class="h4 mb-0">{{ $filterChurch->name }}</h1>
                        <a href="{{ route('churches.show', $filterChurch) }}" class="small">{{ __('Visit church page') }}</a>
                    </div>
                    <a href="{{ route('dashboard') }}" class="btn btn-ff-ghost btn-sm"><i class="bi bi-x-lg"></i> {{ __('All churches') }}</a>
                </div>
                @include('partials.home-slider', ['slides' => $slides, 'sliderId' => 'ff-church-slider'])
            @endif

            @foreach ($widgets['top'] ?? [] as $widget)
                @include($widget['view'], $widget['data'] ?? [])
            @endforeach

            @if ($items->isEmpty())
                @if (empty($user->memberChurchIds()))
                    <x-ff.empty-state icon="bi-house-heart" :title="__('Your feed is waiting')" :message="__('Subscribe to your church to see sermons, live services, events and updates here.')">
                        <a href="{{ route('churches.index') }}" class="btn btn-ff-primary">{{ __('Find your church') }}</a>
                    </x-ff.empty-state>
                @else
                    <x-ff.empty-state icon="bi-cup-hot" :title="__('All caught up')" :message="__('Nothing new yet — check back after the next service.')" />
                @endif
            @else
                <div class="d-grid gap-4">
                    @foreach ($items as $item)
                        @includeIf('member.feed._'.$item['type'], [$item['type'] => $item['model'], 'item' => $item])
                    @endforeach
                </div>
                @if ($next)
                    <div class="text-center mt-4">
                        <a href="{{ request()->fullUrlWithQuery(['before' => $next]) }}" class="btn btn-ff-outline px-4">{{ __('Load older posts') }}</a>
                    </div>
                @endif
            @endif
        </div>

        <aside class="col-xl-4 d-grid gap-4 align-content-start">
            @foreach ($widgets['side'] ?? [] as $widget)
                @include($widget['view'], $widget['data'] ?? [])
            @endforeach

            @if ($suggested->isNotEmpty())
                <x-ff.card :title="__('Churches you may like')">
                    <ul class="list-unstyled mb-0 d-grid gap-3">
                        @foreach ($suggested as $church)
                            <li class="d-flex align-items-center gap-3">
                                <x-ff.church-avatar :church="$church" size="sm" />
                                <div class="flex-grow-1 min-w-0">
                                    <a href="{{ route('churches.show', $church) }}" class="fw-semibold text-ff-black small d-block text-truncate">{{ $church->name }}</a>
                                    <span class="small-xs text-ff-muted">{{ $church->location }}</span>
                                </div>
                                <form method="POST" action="{{ route('churches.subscribe', $church) }}">@csrf
                                    <input type="hidden" name="source" value="suggestion">
                                    <button class="btn btn-sm btn-ff-soft" aria-label="{{ __('Subscribe to :church', ['church' => $church->name]) }}"><i class="bi bi-plus-lg"></i></button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </x-ff.card>
            @endif

            <div class="ff-card ff-card-mist">
                <h2 class="h6"><i class="bi bi-reception-1 text-ff-primary"></i> {{ __('Save data') }}</h2>
                <p class="small mb-2">{{ __('Turn on data-saver mode for audio-only live streams and fewer images.') }}</p>
                <a href="#" data-ff-lite-toggle class="small fw-semibold">{{ ($ffLite ?? false) ? __('Turn off data-saver') : __('Turn on data-saver') }}</a>
            </div>
        </aside>
    </div>
</x-layouts.member>
