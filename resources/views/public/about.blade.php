<x-layouts.public :title="__('About us')" :description="config('faithfellows.brand.mission')">

    <section class="ff-page-hero">
        <div class="container">
            <span class="ff-eyebrow">{{ __('About FaithFellows') }}</span>
            <h1 class="ff-display mt-3 mb-3">{{ __('One App. Many Faith Communities.') }}</h1>
            <p class="ff-lead mb-0" style="max-width:600px">{{ __('Built by Kakebe Technologies in Lira City, Uganda — giving every church its own digital home, and every believer one account for all the churches they love.') }}</p>
        </div>
    </section>

    <section class="ff-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="ff-card h-100 p-4 p-md-5">
                        <div class="ff-feature-icon"><i class="bi bi-bullseye"></i></div>
                        <h2 class="h4 mt-3">{{ __('Mission') }}</h2>
                        <p class="text-ff-muted mb-0">{{ __('Simplify church management, deepen engagement, and expand the Gospel\'s reach — at a price every church can afford.') }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ff-card h-100 p-4 p-md-5 bg-ff-gradient border-0">
                        <div class="ff-feature-icon" style="background:rgba(255,255,255,.18);color:#fff"><i class="bi bi-eye"></i></div>
                        <h2 class="h4 text-white mt-3">{{ __('Vision') }}</h2>
                        <p class="mb-0 text-white" style="opacity:.9">{{ __('Every church in Uganda — and beyond — digitally connected and reaching its community.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ff-section bg-ff-gray">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <span class="ff-eyebrow">{{ __('Why we built this') }}</span>
                    <h2 class="ff-section-title mt-3">{{ __('Ministry deserves better tools.') }}</h2>
                    <p class="text-ff-muted mt-3 mb-0">{{ __('Radio slots are expensive. WhatsApp groups don\'t scale. Mobile money needed a receipt. FaithFellows fixes all three.') }}</p>
                </div>
                <div class="col-lg-7">
                    <div class="row g-3">
                        @foreach ([
                            ['bi-heart',         __('Faith first'),      __('Built for ministry outcomes.')],
                            ['bi-geo-alt',        __('Local by design'),  __('MoMo, low data, local languages.')],
                            ['bi-shield-check',   __('Private & secure'), __('Each church\'s data is isolated.')],
                            ['bi-people',         __('Always supported'), __('Kakebe team behind you, always.')],
                        ] as [$icon, $t, $d])
                            <div class="col-sm-6">
                                <div class="ff-card h-100">
                                    <i class="bi {{ $icon }} fs-3 text-ff-primary"></i>
                                    <h3 class="h6 mt-2 mb-1">{{ $t }}</h3>
                                    <p class="small text-ff-muted mb-0">{{ $d }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ff-section pt-0">
        <div class="container">
            <div class="ff-card ff-card-mist p-4 p-md-5">
                <div class="row g-4 align-items-center">
                    <div class="col-md-8">
                        <h2 class="h5 mb-1">{{ config('faithfellows.company.name') }}</h2>
                        <p class="mb-0 small text-ff-muted">
                            {{ config('faithfellows.company.address') }}<br>
                            <i class="bi bi-telephone"></i> {{ config('faithfellows.company.phone') }} &nbsp;·&nbsp;
                            <i class="bi bi-envelope"></i> {{ config('faithfellows.company.email') }}
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="{{ route('contact') }}" class="btn btn-ff-primary px-4">{{ __('Get in touch') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</x-layouts.public>
