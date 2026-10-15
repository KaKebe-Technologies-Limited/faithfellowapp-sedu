<x-layouts.public>
@php
    $applyUrl = Route::has('church.apply') ? route('church.apply') : route('contact');
    $appLink  = ff_app_download_url();
    $appUrl   = $appLink ?: route('register');
    $trial    = (int) rescue(fn () => \App\Models\PlatformSetting::get('trial_months', config('faithfellows.pricing.free_hosting_months')), config('faithfellows.pricing.free_hosting_months'), false);
    $img      = fn (string $name) => asset('images/home/'.$name.'.jpg');
@endphp

    {{-- ── Hero ───────────────────────────────────────────────────────────── --}}
    <section class="fh-hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="fh-pill"><i class="bi bi-circle-fill"></i> {{ config('faithfellows.brand.tagline') }}</span>
                    <h1 class="fh-title">
                        {{ __('Pray. Give.') }}<br>{{ __('Grow. All in') }}<br><span>FaithFellows.</span>
                    </h1>
                    <p class="fh-sub">{{ __('One simple app for you and your church family.') }}</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="{{ $appUrl }}" class="btn btn-ff-dark btn-lg px-4"><i class="bi bi-download me-2"></i>{{ __('Download the app') }}</a>
                        <a href="{{ $applyUrl }}" class="btn btn-ff-outline btn-lg px-4">{{ __('Register your church') }}</a>
                    </div>
                    <ul class="fh-trust">
                        <li><i class="bi bi-shield-check"></i> {{ __('Safe and private') }}</li>
                        <li><i class="bi bi-lightning-charge"></i> {{ __('Mobile money') }}</li>
                        <li><i class="bi bi-heart"></i> {{ __('Free for members') }}</li>
                    </ul>
                </div>

                <div class="col-lg-6">
                    <div class="fh-art">
                        <div class="fh-art-photo"><img src="{{ $img('hero') }}" alt="" width="760" height="1081" fetchpriority="high"></div>
                        <div class="fh-phone"><img src="{{ $img('phone-home') }}" alt="{{ __('The FaithFellows app') }}" width="460" height="995"></div>
                        <div class="fh-chip"><i class="bi bi-heart-fill"></i> {{ __('Thank you for giving') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Features ───────────────────────────────────────────────────────── --}}
    <section class="container fh-block" id="features">
        <div class="fh-panel">
            <span class="fh-eyebrow">{{ __('Features') }}</span>
            <h2 class="fh-h2">{{ __('Everything in one app') }}</h2>
            <div class="row g-4 mt-1">
                <div class="col-md-4">
                    <div class="fh-feature">
                        <div class="fh-icon"><i class="bi bi-book"></i></div>
                        <h3>{{ __('Read and pray') }}</h3>
                        <p>{{ __('The Bible, hymns and a daily word.') }}</p>
                    </div>
                    <div class="fh-shot"><img src="{{ $img('pray') }}" alt="" loading="lazy" width="640" height="683"></div>
                </div>
                <div class="col-md-4">
                    <div class="fh-feature">
                        <div class="fh-icon"><i class="bi bi-heart"></i></div>
                        <h3>{{ __('Give with joy') }}</h3>
                        <p>{{ __('Tithes and offerings by mobile money.') }}</p>
                    </div>
                    <div class="fh-shot is-phone"><div class="fh-phone is-flat"><img src="{{ $img('phone-give') }}" alt="" loading="lazy" width="460" height="995"></div></div>
                </div>
                <div class="col-md-4">
                    <div class="fh-feature">
                        <div class="fh-icon"><i class="bi bi-people"></i></div>
                        <h3>{{ __('Belong') }}</h3>
                        <p>{{ __('Groups, games and friends in faith.') }}</p>
                    </div>
                    <div class="fh-shot"><img src="{{ $img('praise') }}" alt="" loading="lazy" width="640" height="626"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── How it works ───────────────────────────────────────────────────── --}}
    <section class="container fh-block text-center" id="how">
        <span class="fh-eyebrow">{{ __('How it works') }}</span>
        <h2 class="fh-h2">{{ __('Start in 3 steps') }}</h2>
        <div class="fh-steps">
            @foreach ([
                ['bi-download',    __('Get the app'),        __('Install FaithFellows.')],
                ['bi-person-plus', __('Follow your church'), __('Find it and tap Follow.')],
                ['bi-stars',       __('Read, give, grow'),   __('Every day, together.')],
            ] as $i => [$icon, $title, $text])
                <div class="fh-step">
                    <div class="fh-step-icon"><i class="bi {{ $icon }}"></i></div>
                    <h3><span>{{ $i + 1 }}</span> {{ $title }}</h3>
                    <p>{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ── For churches ───────────────────────────────────────────────────── --}}
    <section class="container fh-block">
        <div class="fh-churches">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <span class="fh-eyebrow text-start">{{ __('For churches') }}</span>
                    <h2 class="fh-h2 text-start">{{ __('Bring your church online') }}</h2>
                    <ul class="fh-checks">
                        <li><i class="bi bi-check-circle-fill"></i> {{ __('Your own website and app') }}</li>
                        <li><i class="bi bi-check-circle-fill"></i> {{ __('Giving by mobile money') }}</li>
                        <li><i class="bi bi-check-circle-fill"></i> {{ __('Records and reports') }}</li>
                    </ul>
                    <a href="{{ $applyUrl }}" class="btn btn-ff-primary btn-lg px-4">{{ __('Register your church') }} <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
                <div class="col-lg-5">
                    <div class="fh-price">
                        <span>{{ __('From') }}</span>
                        <strong>UGX 60,000</strong>
                        <span>{{ __('a month') }}</span>
                        @if ($trial >= 12)
                            <em>{{ __('First year free') }}</em>
                        @elseif ($trial > 0)
                            <em>{{ __('First :n months free', ['n' => $trial]) }}</em>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Get the app ────────────────────────────────────────────────────── --}}
    <section class="container fh-block pb-5">
        <div class="fh-cta">
            <div class="fh-cta-photo"><img src="{{ $img('hero') }}" alt="" loading="lazy" width="760" height="1081"></div>
            <div class="fh-cta-body">
                <h2>{{ __('Join your church family') }}</h2>
                <p>{{ __('Free for every member.') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ $appUrl }}" class="fh-store" @if ($appLink) rel="noopener" @endif>
                        <i class="bi bi-google-play"></i><span><small>{{ __('Get it on') }}</small>{{ __('Android') }}</span>
                    </a>
                    <span class="fh-store is-soon" aria-disabled="true">
                        <i class="bi bi-apple"></i><span><small>{{ __('Coming soon') }}</small>{{ __('iPhone') }}</span>
                    </span>
                </div>
            </div>
        </div>
    </section>

</x-layouts.public>
