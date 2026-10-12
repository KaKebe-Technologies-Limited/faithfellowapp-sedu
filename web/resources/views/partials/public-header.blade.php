@php
    $nav = [
        ['label' => __('Home'),     'route' => 'home'],
        ['label' => __('About'),    'route' => 'about'],
        ['label' => __('Services'), 'route' => 'services'],
        ['label' => __('Churches'), 'route' => 'churches.index'],
        ['label' => __('Pricing'),  'route' => 'pricing'],
        ['label' => __('Blog'),     'route' => 'blog.index'],
        ['label' => __('Contact'),  'route' => 'contact'],
    ];
    $company = config('faithfellows.company');
@endphp

<header class="ff-header-wrap">

    {{-- ── Top contact bar ──────────────────────────────── --}}
    <div class="ff-topbar d-none d-lg-block">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex gap-4 align-items-center">
                    <a href="tel:{{ $company['phone'] }}" class="ff-topbar-link">
                        <i class="bi bi-telephone-fill"></i> {{ $company['phone'] }}
                    </a>
                    <a href="mailto:{{ $company['email'] }}" class="ff-topbar-link">
                        <i class="bi bi-envelope-fill"></i> {{ $company['email'] }}
                    </a>
                    <span class="ff-topbar-link">
                        <i class="bi bi-geo-alt-fill"></i> Lira City, Uganda
                    </span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <a href="https://www.facebook.com/" class="ff-topbar-social" aria-label="Facebook" target="_blank" rel="noopener"><i class="bi bi-facebook"></i></a>
                    <a href="https://x.com/" class="ff-topbar-social" aria-label="X / Twitter" target="_blank" rel="noopener"><i class="bi bi-twitter-x"></i></a>
                    <a href="https://www.instagram.com/" class="ff-topbar-social" aria-label="Instagram" target="_blank" rel="noopener"><i class="bi bi-instagram"></i></a>
                    <a href="https://wa.me/256{{ ltrim($company['phone'], '0') }}" class="ff-topbar-social" aria-label="WhatsApp" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i></a>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Main navigation ───────────────────────────────── --}}
    <nav class="ff-navbar navbar navbar-expand-lg" aria-label="{{ __('Main navigation') }}">
        <div class="container">
            <x-logo :href="route('home')" :size="34" class="navbar-brand me-4" />

            <button class="navbar-toggler border-0 shadow-none p-0" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#publicNav"
                    aria-controls="publicNav" aria-label="{{ __('Open menu') }}">
                <i class="bi bi-list fs-2"></i>
            </button>

            <div class="offcanvas offcanvas-end" tabindex="-1" id="publicNav" aria-labelledby="publicNavLabel">
                <div class="offcanvas-header border-bottom">
                    <x-logo :size="30" id="publicNavLabel" />
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="offcanvas-body align-items-lg-center">
                    <ul class="navbar-nav me-auto gap-lg-1">
                        @foreach ($nav as $item)
                            @if (Route::has($item['route']))
                                <li class="nav-item">
                                    <a href="{{ route($item['route']) }}"
                                       @class(['nav-link ff-nav-link', 'active' => request()->routeIs($item['route'])
                                           || ($item['route'] !== 'home' && request()->routeIs($item['route'].'.*'))])
                                       @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                    <div class="d-flex flex-column flex-lg-row gap-2 mt-4 mt-lg-0">
                        @auth
                            <a href="{{ auth()->user()->homeUrl() }}" class="btn btn-ff-primary px-4">
                                <i class="bi bi-grid-1x2 me-1"></i> {{ __('Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}"   class="btn btn-ff-ghost px-4">{{ __('Log in') }}</a>
                            <a href="{{ route('register') }}" class="btn btn-ff-primary px-4">{{ __('Get started') }}</a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </nav>

</header>
