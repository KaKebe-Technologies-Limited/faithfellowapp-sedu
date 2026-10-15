@php
    $appUrl = ff_app_download_url() ?: route('register');
    $nav = [
        ['label' => __('Features'),     'url' => route('home').'#features',  'route' => null],
        ['label' => __('How it works'), 'url' => route('home').'#how',       'route' => null],
        ['label' => __('Pricing'),      'url' => route('pricing'),           'route' => 'pricing'],
        ['label' => __('Churches'),     'url' => Route::has('churches.index') ? route('churches.index') : null, 'route' => 'churches.index'],
        ['label' => __('Blog'),         'url' => route('blog.index'),        'route' => 'blog.index'],
    ];
@endphp

{{-- A slim line above the menu: one short message, nothing else. --}}
<div class="ff-bar" role="note">
    <div class="container d-flex justify-content-between align-items-center gap-3">
        <span><i class="bi bi-lightning-charge-fill"></i> {{ __('FaithFellows is live. Worship, give and grow with your church.') }}</span>
        <span class="d-none d-md-inline"><i class="bi bi-geo-alt-fill"></i> {{ __('Made in Uganda') }}</span>
    </div>
</div>

<header class="ff-header-wrap">
    <nav class="ff-navbar navbar navbar-expand-lg" aria-label="{{ __('Main navigation') }}">
        <div class="container">
            <x-logo :href="route('home')" :size="34" class="navbar-brand me-lg-5" />

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
                    <ul class="navbar-nav mx-lg-auto gap-lg-2">
                        @foreach ($nav as $item)
                            @continue(! $item['url'])
                            <li class="nav-item">
                                <a href="{{ $item['url'] }}" @class(['nav-link ff-nav-link', 'active' => $item['route'] && request()->routeIs($item['route'].'*')])
                                   @if ($item['route'] && request()->routeIs($item['route'])) aria-current="page" @endif>
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 mt-4 mt-lg-0">
                        @auth
                            <a href="{{ auth()->user()->homeUrl() }}" class="btn btn-ff-primary px-4">
                                <i class="bi bi-grid-1x2 me-1"></i> {{ __('Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="ff-nav-login">{{ __('Log in') }}</a>
                            <a href="{{ $appUrl }}" class="btn btn-ff-dark px-4" @if (ff_app_download_url()) rel="noopener" @endif>{{ __('Download app') }}</a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>
