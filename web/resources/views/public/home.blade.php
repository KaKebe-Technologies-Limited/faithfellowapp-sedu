<x-layouts.public>
@php
    $applyUrl = Route::has('church.apply') ? route('church.apply') : route('contact');
    $findUrl  = Route::has('churches.index') ? route('churches.index') : route('register');

    // Unsplash CDN — free for web display, no API key required.
    // Format: https://images.unsplash.com/photo-{ID}?w={width}&q=80&fit=crop&auto=format
    $photos = [
        'worship'    => 'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?w=800&q=80&fit=crop&auto=format',
        'community'  => 'https://images.unsplash.com/photo-1529070538774-1843cb3265df?w=600&q=80&fit=crop&auto=format',
        'giving'     => 'https://images.unsplash.com/photo-1607962837359-5e7e89f86776?w=600&q=80&fit=crop&auto=format',
        'prayer'     => 'https://images.unsplash.com/photo-1508672019048-805c876b67e2?w=600&q=80&fit=crop&auto=format',
        'family'     => 'https://images.unsplash.com/photo-1445116572660-236099ec97a0?w=900&q=80&fit=crop&auto=format',
        'whyus'      => 'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=900&q=80&fit=crop&auto=format',
        'pastor'     => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=200&q=80&fit=crop&auto=format',
        'woman1'     => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=200&q=80&fit=crop&auto=format',
        'man1'       => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=200&q=80&fit=crop&auto=format',
    ];
@endphp

    {{-- ══════════════════════════════════════════════════════════
         HERO
         ══════════════════════════════════════════════════════════ --}}
    <section class="ff-hero-v2">
        <div class="container">
            <div class="row align-items-center g-5">

                <div class="col-lg-6">
                    <span class="ff-eyebrow mb-3">{{ __('One app. Many faith communities.') }}</span>
                    <h1 class="ff-hero-title mt-3">
                        {{ __('One Account.') }}<br>
                        <em class="ff-hero-italic">{{ __('Every Church') }}<br>{{ __('You Love.') }}</em>
                    </h1>
                    <p class="ff-hero-sub mt-4 mb-4">
                        {{ __('Sign up once and follow as many churches as you want. Churches get a full digital home — website, sermons, live streams, giving and member care — all in one place.') }}
                    </p>
                    <div class="d-flex flex-wrap gap-3 mb-5">
                        <a href="{{ $applyUrl }}" class="btn btn-ff-primary btn-lg px-4 py-3">
                            {{ __('Register your church') }} <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                        <a href="{{ $findUrl }}" class="btn btn-ff-outline btn-lg px-4 py-3">
                            {{ __('Find a church') }}
                        </a>
                    </div>
                    <div class="ff-hero-trust">
                        <div class="ff-avatar-stack me-3">
                            <img src="{{ $photos['pastor'] }}" class="ff-avatar ff-avatar-sm" alt="member">
                            <img src="{{ $photos['woman1'] }}" class="ff-avatar ff-avatar-sm" alt="member">
                            <img src="{{ $photos['man1'] }}"   class="ff-avatar ff-avatar-sm" alt="member">
                            <span class="ff-avatar ff-avatar-sm">+</span>
                        </div>
                        <div>
                            <strong class="ff-hero-trust-num">{{ ff_short_number(max($stats['members'],0)) }}+</strong>
                            <span>{{ __('Believers already connected') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Photo collage --}}
                <div class="col-lg-6">
                    <div class="ff-collage">
                        <div class="ff-collage-main">
                            <img src="{{ $photos['worship'] }}" alt="{{ __('Church worship') }}" class="w-100 h-100 object-fit-cover" loading="eager">
                            <div class="ff-collage-live-badge">
                                <span class="ff-badge-live">{{ __('Live') }}</span>
                                <span class="ms-2 small text-white fw-semibold">Sunday Service</span>
                            </div>
                        </div>
                        <div class="ff-collage-top-right">
                            <div class="ff-collage-img">
                                <img src="{{ $photos['community'] }}" alt="{{ __('Community') }}" class="w-100 h-100 object-fit-cover" loading="lazy">
                            </div>
                            <div class="ff-collage-img">
                                <img src="{{ $photos['giving'] }}" alt="{{ __('Giving') }}" class="w-100 h-100 object-fit-cover" loading="lazy">
                            </div>
                        </div>
                        <div class="ff-collage-bottom-right">
                            <img src="{{ $photos['prayer'] }}" alt="{{ __('Prayer') }}" class="w-100 h-100 object-fit-cover" loading="lazy">
                        </div>
                        <div class="ff-collage-stat-card">
                            <div class="ff-collage-stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                            <div>
                                <div class="fw-bold text-ff-black" style="font-size:.9rem">UGX {{ ff_short_number(max($stats['giving'] ?? 0, 12400000)) }}</div>
                                <div class="text-ff-muted" style="font-size:.72rem">{{ __('Given this month') }}</div>
                            </div>
                            <span class="ff-collage-stat-trend"><i class="bi bi-arrow-up-right"></i> 18%</span>
                        </div>
                        <div class="ff-collage-churches-card">
                            <div class="fw-bold" style="font-size:1.1rem;color:#fff">{{ ff_short_number(max($stats['churches'],0)) }}+</div>
                            <div style="font-size:.72rem;color:rgba(255,255,255,.75)">{{ __('Churches') }}</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════
         TRUST STRIP
         ══════════════════════════════════════════════════════════ --}}
    <section class="ff-trust-strip">
        <div class="container">
            <div class="ff-trust-inner">
                <span class="ff-trust-label">{{ __('One app trusted by many communities') }}</span>
                <div class="ff-trust-logos">
                    @foreach (['Anglican', 'Catholic', 'Pentecostal', 'Baptist', 'Adventist', 'Evangelical'] as $d)
                        <span class="ff-trust-logo-item"><i class="bi bi-building-fill-check me-1"></i>{{ $d }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════
         ABOUT / MISSION
         ══════════════════════════════════════════════════════════ --}}
    <section class="ff-section">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <span class="ff-eyebrow">{{ __('The concept') }}</span>
                    <h2 class="ff-section-title mt-3">
                        {{ __('One app.') }}<br>
                        <span class="text-ff-primary">{{ __('Many faith communities.') }}</span>
                    </h2>
                </div>
                <div class="col-lg-7">
                    <p class="ff-lead mb-4">{{ __('A believer signs up once and subscribes to as many churches as they want — home church, village church, favourite preacher. Every church gets its own full digital space. One email. One account. Many churches.') }}</p>
                    <div class="row g-3">
                        @foreach ([
                            ['bi-patch-check-fill', '10+',                                       __('Years of technology expertise')],
                            ['bi-people-fill',      ff_short_number(max($stats['members'],0)).'+', __('Believers connected')],
                            ['bi-church',           ff_short_number(max($stats['churches'],0)).'+', __('Churches on the platform')],
                            ['bi-shield-check',     '100%',                                      __('Isolated data per church')],
                        ] as [$icon, $num, $text])
                            <div class="col-sm-6">
                                <div class="ff-about-point">
                                    <div class="ff-about-point-icon"><i class="bi {{ $icon }}"></i></div>
                                    <div>
                                        <div class="fw-bold fs-5 text-ff-black">{{ $num }}</div>
                                        <div class="small text-ff-muted">{{ $text }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════
         FAMILY — photo left, bullets right
         ══════════════════════════════════════════════════════════ --}}
    <section class="ff-section ff-section-alt">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <div class="ff-split-img-wrap">
                        <img src="{{ $photos['family'] }}" alt="{{ __('Church family') }}"
                             class="w-100 h-100 object-fit-cover rounded-4" style="height:420px!important" loading="lazy">
                        <div class="ff-split-img-badge">
                            <span class="fs-2 fw-bold text-ff-primary">{{ ff_short_number(max($stats['sermons'],0)) }}+</span>
                            <span class="small text-ff-muted d-block">{{ __('Sermons shared') }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <span class="ff-eyebrow">{{ __('For your whole church family') }}</span>
                    <h2 class="ff-section-title mt-3">
                        {{ __('One platform for every') }}<br>
                        <span class="text-ff-primary">{{ __('ministry moment.') }}</span>
                    </h2>
                    <p class="text-ff-muted mb-4">{{ __('Live streams, building fund campaigns, small group attendance — FaithFellows keeps your community connected and finances transparent.') }}</p>
                    <ul class="ff-bullet-list mb-4">
                        <li><i class="bi bi-check-circle-fill text-ff-primary"></i> {{ __('Giving channels tailored to your church') }}</li>
                        <li><i class="bi bi-check-circle-fill text-ff-primary"></i> {{ __('Mobile money from UGX 500') }}</li>
                        <li><i class="bi bi-check-circle-fill text-ff-primary"></i> {{ __('Real-time receipts & financial reports') }}</li>
                        <li><i class="bi bi-check-circle-fill text-ff-primary"></i> {{ __('Works offline and on feature phones') }}</li>
                    </ul>
                    <a href="{{ $applyUrl }}" class="btn btn-ff-primary px-4">{{ __('Get started') }} <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════
         SERVICES GRID
         ══════════════════════════════════════════════════════════ --}}
    <section class="ff-section" id="services">
        <div class="container">
            <div class="row g-5 align-items-end mb-5">
                <div class="col-lg-6">
                    <span class="ff-eyebrow">{{ __('Our services') }}</span>
                    <h2 class="ff-section-title mt-3 mb-0">
                        {{ __('Comprehensive Coverage,') }}<br>
                        <span class="text-ff-primary">{{ __('Simplified for You.') }}</span>
                    </h2>
                </div>
                <div class="col-lg-6">
                    <p class="text-ff-muted mb-4">{{ __('Every tool your church needs — at a price that makes sense for Uganda.') }}</p>
                    <a href="{{ route('services') }}" class="btn btn-ff-outline px-4">{{ __('Explore all services') }}</a>
                </div>
            </div>
            <div class="row g-4">
                @foreach ([
                    ['bi-broadcast',      __('Live Streaming'),        __('Video or audio-only for low-data users.')],
                    ['bi-heart-pulse',    __('Member Care'),           __('Follow-ups, birthdays and pastoral notes.')],
                    ['bi-phone-vibrate',  __('Mobile Giving'),         __('MTN MoMo, Airtel, Iotec, Stripe & USSD.')],
                    ['bi-flag-fill',      __('Campaigns'),             __('Progress bars, milestones and pledges.')],
                    ['bi-calendar-check', __('Events & Ticketing'),    __('RSVPs, paid tickets and QR check-in.')],
                    ['bi-mic-fill',       __('Sermons'),               __('Audio, video, series. Offline downloads.')],
                    ['bi-chat-heart',     __('Prayer Wall'),           __('"I prayed" counter and prayer chain.')],
                    ['bi-diagram-3-fill', __('Small Groups'),          __('Cells, attendance and group giving.')],
                    ['bi-globe2',         __('Church Website'),        __('SEO-ready with drag-and-drop blocks.')],
                    ['bi-bell-fill',      __('Notifications'),         __('Push, email, SMS, WhatsApp & digests.')],
                    ['bi-graph-up-arrow', __('Analytics'),             __('Giving trends, growth and CSV exports.')],
                    ['bi-person-badge',   __('Roles & Permissions'),   __('Custom staff roles in minutes.')],
                ] as [$icon, $title, $desc])
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <div class="ff-service-card h-100">
                            <div class="ff-service-icon"><i class="bi {{ $icon }}"></i></div>
                            <h3 class="h6 fw-bold mt-3 mb-1">{{ $title }}</h3>
                            <p class="small text-ff-muted mb-0">{{ $desc }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════
         WHY US — points left, photo right
         ══════════════════════════════════════════════════════════ --}}
    <section class="ff-section ff-section-alt">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <span class="ff-eyebrow">{{ __('Why choose FaithFellows') }}</span>
                    <h2 class="ff-section-title mt-3">
                        {{ __('Ministry Made Simple,') }}<br>
                        <span class="text-ff-primary">{{ __('Secure, and Personal.') }}</span>
                    </h2>
                    <p class="text-ff-muted mb-4">{{ __('Modern, reliable tools designed for the African church — built on trust, affordability and local realities.') }}</p>
                    <div class="row g-3 mb-4">
                        @foreach ([
                            ['bi-shield-fill-check', __('Trusted'),     __('Built by Kakebe Technologies, Lira City.')],
                            ['bi-layers-fill',       __('All-in-one'),  __('Website, giving, events, care — one price.')],
                            ['bi-headset',           __('Support 24/7'),__('Always reachable by phone, WhatsApp & email.')],
                            ['bi-phone',             __('Mobile-First'),__('Low data, MoMo and offline from day one.')],
                        ] as [$icon, $title, $desc])
                            <div class="col-sm-6">
                                <div class="ff-why-point">
                                    <div class="ff-why-icon"><i class="bi {{ $icon }}"></i></div>
                                    <h4 class="h6 mb-1">{{ $title }}</h4>
                                    <p class="small text-ff-muted mb-0">{{ $desc }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('about') }}" class="btn btn-ff-primary px-4">{{ __('Learn more about us') }}</a>
                </div>
                <div class="col-lg-6">
                    <div class="ff-why-img-wrap">
                        <img src="{{ $photos['whyus'] }}" alt="{{ __('Why FaithFellows') }}"
                             class="w-100 rounded-4 ff-why-img" loading="lazy">
                        <div class="ff-why-badge-1">
                            <i class="bi bi-lightning-charge-fill text-ff-primary me-1"></i>
                            <span class="small fw-semibold">{{ __('Quick Setup') }}</span>
                        </div>
                        <div class="ff-why-badge-2">
                            <i class="bi bi-clock-fill text-ff-primary me-1"></i>
                            <span class="small fw-semibold">{{ __('Always Available') }}</span>
                        </div>
                        <div class="ff-why-badge-3">
                            <i class="bi bi-phone-fill text-ff-primary me-1"></i>
                            <span class="small fw-semibold">{{ __('Mobile Ready') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════
         TESTIMONIALS
         ══════════════════════════════════════════════════════════ --}}
    <section class="ff-section">
        <div class="container">
            <x-ff.section-title
                :eyebrow="__('Testimonials')"
                :title="__('Trusted by Thousands of Happy Churches')" />
            <div class="row g-4 mt-2">
                @foreach ([
                    [
                        'https://images.unsplash.com/photo-1463453091185-61582044d556?w=100&q=80&fit=crop&auto=format',
                        'Rev. Samuel Okello',
                        __('Senior Pastor'),
                        __('Grace Community Church, Kampala'),
                        __('FaithFellows transformed how we collect tithes. Our members now give via MoMo from anywhere — even those in the diaspora send via card. The receipts are instant and our finance team has never been happier.'),
                    ],
                    [
                        'https://images.unsplash.com/photo-1607746882042-944635dfe10e?w=100&q=80&fit=crop&auto=format',
                        'Pastor Grace Auma',
                        __('Church Administrator'),
                        __('New Life Ministries, Gulu'),
                        __('Our Sunday live stream now reaches members in Nairobi and London. The audio-only mode means even those on slow data never miss a service. It\'s a true blessing.'),
                    ],
                    [
                        'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&q=80&fit=crop&auto=format',
                        'Deacon James Wangolo',
                        __('Finance Lead'),
                        __('Victory Church, Jinja'),
                        __('The campaign tool helped us raise our building fund 68% in three months. The progress bar motivated every giver. We celebrated every milestone with the whole congregation online.'),
                    ],
                ] as [$avatar, $name, $role, $church, $quote])
                    <div class="col-md-4">
                        <div class="ff-testimonial-card h-100">
                            <div class="ff-testimonial-stars mb-3">
                                @for ($s = 0; $s < 5; $s++)<i class="bi bi-star-fill"></i>@endfor
                            </div>
                            <p class="ff-testimonial-quote mb-4">"{{ $quote }}"</p>
                            <div class="d-flex align-items-center gap-3 mt-auto">
                                <img src="{{ $avatar }}" alt="{{ $name }}"
                                     class="ff-avatar ff-avatar-lg" loading="lazy">
                                <div>
                                    <div class="fw-bold text-ff-black" style="font-size:.9rem">{{ $name }}</div>
                                    <div class="small text-ff-primary fw-semibold">{{ $role }}</div>
                                    <div class="small text-ff-muted">{{ $church }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════
         HOW IT WORKS
         ══════════════════════════════════════════════════════════ --}}
    <section class="ff-section ff-section-alt">
        <div class="container">
            <x-ff.section-title :eyebrow="__('How it works')" :title="__('One app. Two experiences.')" />
            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="ff-steps-card h-100">
                        <div class="ff-steps-card-head">
                            <span class="ff-badge ff-badge-primary"><i class="bi bi-building"></i> {{ __('For churches') }}</span>
                        </div>
                        <ol class="ff-steps">
                            @foreach ([
                                [__('Apply online'),      __('Tell us about your church. Approved within 48 hours.')],
                                [__('Set up in minutes'), __('Logo, service times, giving channels and staff roles via a guided wizard.')],
                                [__('Reach your people'), __('Publish sermons, go live, run campaigns and receive gifts the same day.')],
                            ] as $i => [$t, $d])
                                <li class="ff-step-item">
                                    <span class="ff-step-num">{{ $i + 1 }}</span>
                                    <div><h3 class="h6 mb-1">{{ $t }}</h3><p class="small text-ff-muted mb-0">{{ $d }}</p></div>
                                </li>
                            @endforeach
                        </ol>
                        <div class="mt-4">
                            <a href="{{ $applyUrl }}" class="btn btn-ff-primary px-4">{{ __('Register your church') }}</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="ff-steps-card ff-steps-card-primary h-100">
                        <div class="ff-steps-card-head">
                            <span class="ff-badge" style="background:rgba(255,255,255,.18);color:#fff"><i class="bi bi-person-heart"></i> {{ __('For believers') }}</span>
                        </div>
                        <ol class="ff-steps">
                            @foreach ([
                                [__('Sign up once'),            __('One email, one account for every church you love.')],
                                [__('Subscribe to churches'),   __('Follow your home church and favourite preachers.')],
                                [__('Worship, give & connect'), __('Watch live, listen offline, give by MoMo and pray together.')],
                            ] as $i => [$t, $d])
                                <li class="ff-step-item">
                                    <span class="ff-step-num ff-step-num-white">{{ $i + 1 }}</span>
                                    <div>
                                        <h3 class="h6 mb-1 text-white">{{ $t }}</h3>
                                        <p class="small mb-0" style="color:rgba(255,255,255,.8)">{{ $d }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                        <div class="mt-4">
                            <a href="{{ route('register') }}" class="btn btn-ff-white px-4">{{ __('Create free account') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════
         PRICING TEASER
         ══════════════════════════════════════════════════════════ --}}
    <section class="ff-section">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <span class="ff-eyebrow">{{ __('Pricing') }}</span>
                    <h2 class="ff-section-title mt-3">
                        {{ __('Everything your church needs,') }}<br>
                        <span class="text-ff-primary">{{ __('one simple price.') }}</span>
                    </h2>
                    <p class="text-ff-muted mb-4">{{ __('No setup fees. No per-member charges. Cancel anytime.') }}</p>
                    <ul class="ff-bullet-list mb-4">
                        @foreach (array_slice(config('faithfellows.pricing.includes'), 0, 6) as $item)
                            <li><i class="bi bi-check-circle-fill text-ff-primary"></i> {{ $item }}</li>
                        @endforeach
                    </ul>
                    <a href="{{ route('pricing') }}" class="btn btn-ff-outline px-4">{{ __('See full pricing') }}</a>
                </div>
                <div class="col-lg-5 offset-lg-1">
                    <div class="ff-price-card">
                        <div class="ff-price-card-badge">{{ __('Starter plan') }}</div>
                        <div class="ff-price-card-amount">
                            <span class="ff-price-currency">UGX</span>
                            <span class="ff-price-num">60,000</span>
                            <span class="ff-price-period">/{{ __('month') }}</span>
                        </div>
                        <p class="text-ff-muted small mb-4">{{ __('Free hosting for your first year. Optional domain UGX 60,000/year.') }}</p>
                        <a href="{{ $applyUrl }}" class="btn btn-ff-primary w-100 btn-lg">{{ __('Get started today') }}</a>
                        <div class="ff-price-card-footer">
                            <i class="bi bi-lock-fill text-ff-primary"></i> {{ __('Secure · No hidden fees') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════
         STATS BAR
         ══════════════════════════════════════════════════════════ --}}
    <section class="ff-stats-bar">
        <div class="container">
            <div class="row g-4 text-center">
                @foreach ([
                    [max($stats['churches'], 0), __('Churches on platform')],
                    [max($stats['members'],  0), __('Believers connected')],
                    [max($stats['sermons'],  0), __('Sermons published')],
                    ['4',                         __('Languages supported')],
                ] as [$n, $label])
                    <div class="col-6 col-md-3">
                        <div class="ff-stat-num">{{ is_numeric($n) ? ff_short_number($n) : $n }}{{ is_numeric($n) && $n > 0 ? '+' : '' }}</div>
                        <div class="ff-stat-label-bar">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════
         BLOG
         ══════════════════════════════════════════════════════════ --}}
    @if ($posts->isNotEmpty())
        <section class="ff-section">
            <div class="container">
                <div class="d-flex justify-content-between align-items-end mb-5 flex-wrap gap-3">
                    <div>
                        <span class="ff-eyebrow">{{ __('Resources') }}</span>
                        <h2 class="ff-section-title mt-2 mb-0">{{ __('From the blog') }}</h2>
                    </div>
                    <a href="{{ route('blog.index') }}" class="btn btn-ff-outline px-4">{{ __('All articles') }} <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
                <div class="row g-4">
                    @foreach ($posts as $post)
                        <div class="col-md-4">@include('public.blog._card', ['post' => $post])</div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ══════════════════════════════════════════════════════════
         FINAL CTA
         ══════════════════════════════════════════════════════════ --}}
    <section class="ff-section">
        <div class="container">
            <div class="ff-cta-v2">
                <div class="ff-cta-v2-glow"></div>
                <div class="row align-items-center g-4 position-relative" style="z-index:1">
                    <div class="col-lg-8">
                        <h2 class="text-white mb-2">{{ __('One app. Your whole faith community.') }}</h2>
                        <p class="mb-0" style="color:rgba(255,255,255,.85);font-size:1.05rem">
                            {{ __('Churches across Uganda are already on FaithFellows. Yours should be too.') }}
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end d-flex flex-column flex-lg-row-reverse gap-3">
                        <a href="{{ $applyUrl }}" class="btn btn-ff-white btn-lg px-4">{{ __('Register your church') }}</a>
                        <a href="{{ route('contact') }}" class="btn btn-lg px-4" style="color:#fff;border:1.5px solid rgba(255,255,255,.4)">{{ __('Talk to us') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</x-layouts.public>
