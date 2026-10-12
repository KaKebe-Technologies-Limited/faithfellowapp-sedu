@php
    $nav = [
        ['label' => __('Home'), 'route' => 'home'],
        ['label' => __('Services'), 'route' => 'services'],
        ['label' => __('Churches'), 'route' => 'churches.index'],
        ['label' => __('Pricing'), 'route' => 'pricing'],
        ['label' => __('Blog'), 'route' => 'blog.index'],
        ['label' => __('About'), 'route' => 'about'],
        ['label' => __('Contact'), 'route' => 'contact'],
    ];
@endphp
<header class="ff-header">
    <nav class="navbar navbar-expand-lg" aria-label="{{ __('Main navigation') }}">
        <div class="container">
            <x-logo :href="route('home')" :size="34" class="navbar-brand me-4" />

            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#publicNav" aria-controls="publicNav" aria-label="{{ __('Open menu') }}">
                <i class="bi bi-list fs-2"></i>
            </button>

            <div class="offcanvas offcanvas-end" tabindex="-1" id="publicNav" aria-labelledby="publicNavLabel">
                <div class="offcanvas-header">
                    <x-logo :size="30" id="publicNavLabel" />
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="offcanvas-body align-items-lg-center">
                    <ul class="navbar-nav me-auto gap-lg-1">
                        @foreach ($nav as $item)
                            @if (Route::has($item['route']))
                                <li class="nav-item">
                                    <a href="{{ route($item['route']) }}" @class(['nav-link', 'active' => request()->routeIs($item['route'], $item['route'].'.*') && $item['route'] !== 'home' || ($item['route'] === 'home' && request()->routeIs('home'))]) @if (request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                    <div class="d-flex flex-column flex-lg-row gap-2 mt-4 mt-lg-0">
                        @auth
                            <a href="{{ auth()->user()->homeUrl() }}" class="btn btn-ff-primary px-4">
                                <i class="bi bi-grid-1x2 me-1"></i> {{ __('Open dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-ff-ghost px-4">{{ __('Log in') }}</a>
                            <a href="{{ route('register') }}" class="btn btn-ff-primary px-4">{{ __('Get started') }}</a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>
