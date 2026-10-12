<x-layouts.public>
    @php
        $applyUrl = Route::has('church.apply') ? route('church.apply') : route('contact');
        $findUrl = Route::has('churches.index') ? route('churches.index') : route('register');
    @endphp

    {{-- ── Hero ─────────────────────────────────────────────── --}}
    <section class="ff-hero">
        <x-logo-mark class="ff-hero-mark" :size="420" />
        <div class="container position-relative">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="ff-eyebrow">{{ __('Church management & engagement platform') }}</span>
                    <h1 class="ff-display mt-3 mb-4">{{ __('Empowering Your Ministry in the') }} <span class="text-ff-primary">{{ __('Digital Age') }}</span></h1>
                    <p class="ff-lead mb-4" style="max-width: 540px">
                        {{ __('Every church gets its own space — website, sermons, live streams, events, giving, prayer and member care — all in one neat system. Built for Uganda: mobile money, low data and SMS ready.') }}
                    </p>
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a href="{{ $applyUrl }}" class="btn btn-ff-primary btn-lg px-4">{{ __('Register your church') }} <i class="bi bi-arrow-right ms-1"></i></a>
                        <a href="{{ $findUrl }}" class="btn btn-ff-outline btn-lg px-4">{{ __('Find a church') }}</a>
                    </div>
                    <div class="d-flex flex-wrap gap-3 small text-ff-muted align-items-center">
                        <span><i class="bi bi-check-circle-fill text-ff-primary"></i> {{ __('UGX 60,000/month') }}</span>
                        <span><i class="bi bi-check-circle-fill text-ff-primary"></i> {{ __('Free hosting for the first year') }}</span>
                        <span><i class="bi bi-check-circle-fill text-ff-primary"></i> {{ __('No app to build') }}</span>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="row g-3 align-items-center">
                        <div class="col-7">
                            <div class="ff-phone-mock">
                                <div class="ff-phone-screen">
                                    <div class="bg-ff-gradient p-3 pb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <x-logo-mark :size="22" class="text-white" />
                                            <i class="bi bi-bell text-white"></i>
                                        </div>
                                        <div class="small-xs text-white-50">{{ __('Sunday Service') }}</div>
                                        <div class="fw-bold text-white" style="font-size:.95rem">Grace Community Church</div>
                                        <span class="ff-badge-live mt-2" style="font-size:.6rem">{{ __('Live') }}</span>
                                    </div>
                                    <div class="p-3 d-grid gap-2" style="margin-top:-14px">
                                        <div class="bg-white rounded-3 shadow-sm p-2 small">
                                            <div class="fw-semibold text-ff-black" style="font-size:.75rem">{{ __('Building Fund 2026') }}</div>
                                            <div class="ff-progress ff-progress-sm my-1"><div class="ff-progress-bar" style="width:68%"></div></div>
                                            <div class="d-flex justify-content-between" style="font-size:.6rem"><span>UGX 68M</span><span>68%</span></div>
                                        </div>
                                        <div class="bg-white rounded-3 shadow-sm p-2 d-flex gap-2 align-items-center">
                                            <span class="ff-avatar ff-avatar-sm ff-avatar-square"><i class="bi bi-play-fill"></i></span>
                                            <div style="font-size:.65rem"><div class="fw-semibold text-ff-black">{{ __('Walking by Faith') }}</div><div class="text-ff-muted">Rev. Okello · 42 min</div></div>
                                        </div>
                                        <div class="bg-white rounded-3 shadow-sm p-2" style="font-size:.65rem">
                                            <div class="fw-semibold text-ff-black mb-1"><i class="bi bi-chat-heart text-ff-primary"></i> {{ __('Prayer wall') }}</div>
                                            <div class="text-ff-muted">{{ __('“Pray for my exams this week…”') }}</div>
                                            <div class="text-ff-primary fw-semibold mt-1">🙏 24 {{ __('prayed') }}</div>
                                        </div>
                                        <div class="btn btn-ff-primary btn-sm w-100" style="font-size:.7rem">{{ __('Give with MoMo') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-5 d-grid gap-3 ff-decorative">
                            <div class="ff-card ff-card-sm">
                                <div class="small text-ff-muted">{{ __('This month') }}</div>
                                <div class="fw-bold fs-5 text-ff-black">UGX 12.4M</div>
                                <div class="small text-success fw-semibold"><i class="bi bi-arrow-up-right"></i> 18%</div>
                            </div>
                            <div class="ff-card ff-card-sm text-center">
                                <x-ff.ring :value="76" :size="96" :stroke="9" />
                            </div>
                            <div class="ff-card ff-card-sm">
                                <div class="d-flex gap-2 align-items-center"><span class="ff-method-icon ff-method-mtn rounded-2 d-inline-flex align-items-center justify-content-center fw-bold" style="width:28px;height:28px;font-size:.55rem">MTN</span><span class="ff-method-icon ff-method-airtel rounded-2 d-inline-flex align-items-center justify-content-center fw-bold text-white" style="width:28px;height:28px;font-size:.5rem">airtel</span><i class="bi bi-credit-card-2-front fs-4"></i></div>
                                <div class="small text-ff-muted mt-1">{{ __('Mobile money & card') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Stats ────────────────────────────────────────────── --}}
    <section class="ff-section-sm border-top border-bottom bg-white">
        <div class="container">
            <div class="row text-center g-4">
                @foreach ([
                    [max($stats['churches'], 0), __('Churches on FaithFellows')],
                    [max($stats['members'], 0), __('Believers connected')],
                    [max($stats['sermons'], 0), __('Sermons shared')],
                    ['4', __('Languages: English, Luo, Luganda, Swahili')],
                ] as [$n, $label])
                    <div class="col-6 col-md-3">
                        <div class="fs-2 fw-bold text-ff-black">{{ is_numeric($n) ? ff_short_number($n) : $n }}{{ is_numeric($n) && $n > 0 ? '+' : '' }}</div>
                        <div class="small text-ff-muted">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Problem ──────────────────────────────────────────── --}}
    <section class="ff-section">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <span class="ff-eyebrow">{{ __('The challenge') }}</span>
                    <h2 class="mt-3 mb-0" style="font-size: clamp(1.75rem, 3.6vw, 2.25rem)">{{ __('Ministry has moved online. Has your church?') }}</h2>
                </div>
                <div class="col-lg-7">
                    <blockquote class="ff-card ff-card-mist fs-5 mb-0 lh-base text-ff-black">
                        “{{ config('faithfellows.brand.problem') }}”
                    </blockquote>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Features ─────────────────────────────────────────── --}}
    <section class="ff-section bg-ff-gray" id="features">
        <div class="container">
            <x-ff.section-title :eyebrow="__('The solution')" :title="__('One platform for every ministry need')"
                :lead="__('Churches get a complete digital back office. Believers get one app for all the churches they love.')" />
            <div class="row g-4">
                @foreach ([
                    ['bi-globe2', __('Church website'), __('A beautiful, SEO-ready website at yourchurch.faithfellows.app — no coding.')],
                    ['bi-mic', __('Sermons & devotionals'), __('Audio, video, notes and series. Members can download for offline listening.')],
                    ['bi-broadcast', __('Live streaming'), __('Go live with video or data-light audio-only mode. Subscribers are notified instantly.')],
                    ['bi-phone-vibrate', __('Mobile money giving'), __('MTN MoMo, Airtel Money, Iotec, card, bank and USSD with instant receipts.')],
                    ['bi-flag', __('Campaigns & fundraising'), __('Building funds, missions and welfare with live progress, milestones and pledges.')],
                    ['bi-calendar-event', __('Events & ticketing'), __('RSVPs, paid tickets, reminders and QR check-in at the door.')],
                    ['bi-chat-heart', __('Prayer wall'), __('Public, church-only or pastor-only requests with an “I prayed” counter.')],
                    ['bi-diagram-3', __('Small groups'), __('Cells and home fellowships with leaders, attendance and group giving.')],
                    ['bi-heart-pulse', __('Member care'), __('Follow-ups, birthdays, pastoral notes, welfare and counseling bookings.')],
                    ['bi-person-badge', __('Roles & permissions'), __('Admin, editor, finance, media and moderator roles — or build your own.')],
                    ['bi-bell', __('Smart notifications'), __('Push, email, SMS and WhatsApp with quiet hours and daily digests.')],
                    ['bi-graph-up-arrow', __('Analytics & reports'), __('Giving trends, engagement and growth with CSV/PDF exports.')],
                ] as [$icon, $title, $text])
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <div class="ff-card ff-card-hover h-100">
                            <div class="ff-feature-icon"><i class="bi {{ $icon }}"></i></div>
                            <h3 class="h6 fw-bold">{{ $title }}</h3>
                            <p class="small text-ff-muted mb-0">{{ $text }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── How it works ────────────────────────────────────── --}}
    <section class="ff-section">
        <div class="container">
            <x-ff.section-title :eyebrow="__('How it works')" :title="__('Simple for churches. Simple for believers.')" />
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="ff-card h-100 p-4 p-md-5">
                        <span class="ff-badge ff-badge-primary mb-3"><i class="bi bi-building"></i> {{ __('For churches') }}</span>
                        <ol class="list-unstyled d-grid gap-4 mb-0">
                            @foreach ([
                                [__('Apply online'), __('Tell us about your church. Our team approves you within 48 hours.')],
                                [__('Set up in minutes'), __('Add your logo, service times, giving channels and staff roles with a guided wizard.')],
                                [__('Reach your people'), __('Publish sermons, go live, run campaigns and receive gifts the same day.')],
                            ] as $i => [$t, $d])
                                <li class="d-flex gap-3">
                                    <span class="ff-avatar ff-avatar-sm flex-shrink-0">{{ $i + 1 }}</span>
                                    <div><h3 class="h6 mb-1">{{ $t }}</h3><p class="small text-ff-muted mb-0">{{ $d }}</p></div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="ff-card h-100 p-4 p-md-5 bg-ff-gradient border-0">
                        <span class="ff-badge mb-3" style="background:rgba(255,255,255,.18);color:#fff"><i class="bi bi-person-heart"></i> {{ __('For believers') }}</span>
                        <ol class="list-unstyled d-grid gap-4 mb-0">
                            @foreach ([
                                [__('Sign up once'), __('One email, one account — for every church you love.')],
                                [__('Subscribe to churches'), __('Follow your home church, your village church and your favourite preachers.')],
                                [__('Worship, give & connect'), __('Watch live, listen offline, give by MoMo and pray together. Unsubscribe anytime.')],
                            ] as $i => [$t, $d])
                                <li class="d-flex gap-3">
                                    <span class="ff-avatar ff-avatar-sm flex-shrink-0" style="background:#fff;color:var(--ff-primary)">{{ $i + 1 }}</span>
                                    <div><h3 class="h6 mb-1 text-white">{{ $t }}</h3><p class="small mb-0 text-white" style="opacity:.85">{{ $d }}</p></div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Built for Uganda ────────────────────────────────── --}}
    <section class="ff-section bg-ff-mist">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <x-ff.section-title align="start" :eyebrow="__('Built for Uganda first')" :title="__('Works on every phone, on every network')"
                        :lead="__('FaithFellows is designed for the realities of African ministry — expensive data, feature phones and mobile money.')" />
                    <a href="{{ route('services') }}" class="btn btn-ff-primary px-4">{{ __('Explore all services') }}</a>
                </div>
                <div class="col-lg-7">
                    <div class="row g-3">
                        @foreach ([
                            ['bi-reception-1', __('Data-saver mode'), __('Audio-only streams use about a tenth of the data. Text-first pages with lazy images.')],
                            ['bi-chat-square-text', __('SMS & USSD'), __('Announcements by SMS and USSD giving for feature phones.')],
                            ['bi-whatsapp', __('WhatsApp'), __('Daily verses and church updates where your people already are.')],
                            ['bi-translate', __('Your language'), __('English, Luo, Luganda and Swahili ready.')],
                        ] as [$icon, $title, $text])
                            <div class="col-sm-6">
                                <div class="ff-card h-100">
                                    <i class="bi {{ $icon }} fs-3 text-ff-primary"></i>
                                    <h3 class="h6 mt-2">{{ $title }}</h3>
                                    <p class="small text-ff-muted mb-0">{{ $text }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Pricing teaser ──────────────────────────────────── --}}
    <section class="ff-section">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <x-ff.section-title align="start" :eyebrow="__('Pricing')" :title="__('Everything your church needs, one simple price')"
                        :lead="__('No setup fees. No per-member charges. Cancel anytime.')" />
                    <ul class="ff-check-list">
                        @foreach (array_slice(config('faithfellows.pricing.includes'), 0, 6) as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="col-lg-5 offset-lg-1">
                    <div class="ff-card p-4 p-md-5 text-center ff-shadow-primary">
                        <span class="ff-badge ff-badge-primary">{{ __('Starter plan') }}</span>
                        <div class="my-3"><span class="fs-5 fw-semibold text-ff-muted">UGX</span> <span class="ff-display">60,000</span><span class="text-ff-muted">/{{ __('month') }}</span></div>
                        <p class="text-ff-muted">{{ __('Free hosting for your first year. Optional domain UGX 60,000/year.') }}</p>
                        <a href="{{ route('pricing') }}" class="btn btn-ff-primary w-100 btn-lg">{{ __('See full pricing') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Blog ────────────────────────────────────────────── --}}
    @if ($posts->isNotEmpty())
        <section class="ff-section bg-ff-gray">
            <div class="container">
                <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
                    <x-ff.section-title align="start" class="mb-0" :eyebrow="__('Resources')" :title="__('From the blog')" />
                    <a href="{{ route('blog.index') }}" class="btn btn-ff-outline">{{ __('All articles') }}</a>
                </div>
                <div class="row g-4">
                    @foreach ($posts as $post)
                        <div class="col-md-4">@include('public.blog._card', ['post' => $post])</div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ── CTA ─────────────────────────────────────────────── --}}
    <section class="ff-section">
        <div class="container">
            <div class="ff-cta-band">
                <div class="row align-items-center g-4 position-relative" style="z-index:1">
                    <div class="col-lg-8">
                        <h2 class="mb-2">{{ __('Ready to take your ministry digital?') }}</h2>
                        <p class="mb-0 fs-5" style="opacity:.9">{{ __('Join churches across Uganda already reaching more people with FaithFellows.') }}</p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <a href="{{ $applyUrl }}" class="btn btn-ff-white btn-lg px-4">{{ __('Get started today') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
