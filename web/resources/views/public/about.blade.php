<x-layouts.public :title="__('About us')" :description="config('faithfellows.brand.mission')">
    <section class="ff-page-hero">
        <div class="container">
            <span class="ff-eyebrow">{{ __('About FaithFellows') }}</span>
            <h1 class="ff-display mt-3 mb-3" style="max-width: 820px">{{ __('Technology that serves the Church') }}</h1>
            <p class="ff-lead mb-0" style="max-width: 720px">{{ __('FaithFellows is built by :company in Lira City, Uganda — a team of engineers and believers who want every church, big or small, to thrive in the digital age.', ['company' => config('faithfellows.company.name')]) }}</p>
        </div>
    </section>

    <section class="ff-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="ff-card h-100 p-4 p-md-5">
                        <div class="ff-feature-icon"><i class="bi bi-bullseye"></i></div>
                        <h2 class="h4">{{ __('Our mission') }}</h2>
                        <p class="ff-lead mb-0">{{ config('faithfellows.brand.mission') }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ff-card h-100 p-4 p-md-5 bg-ff-gradient border-0">
                        <div class="ff-feature-icon" style="background:rgba(255,255,255,.18);color:#fff"><i class="bi bi-eye"></i></div>
                        <h2 class="h4 text-white">{{ __('Our vision') }}</h2>
                        <p class="fs-5 mb-0 text-white" style="opacity:.92">{{ config('faithfellows.brand.vision') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ff-section bg-ff-gray">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <x-ff.section-title align="start" :eyebrow="__('Our story')" :title="__('Why we built FaithFellows')" />
                    <div class="ff-prose" style="font-size:1.05rem">
                        <p>{{ config('faithfellows.brand.problem') }}</p>
                        <p>{{ __('We saw pastors spending heavily on radio slots, treasurers reconciling mobile money by hand, and believers juggling WhatsApp groups to keep up with their churches. FaithFellows brings all of it together: one shared platform where every church gets its own space, and every believer needs just one account.') }}</p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="row g-3">
                        @foreach ([
                            ['bi-heart', __('Faith first'), __('We build for ministry outcomes, not vanity metrics.')],
                            ['bi-geo-alt', __('Local by design'), __('Mobile money, low bandwidth and local languages from day one.')],
                            ['bi-shield-check', __('Trust & privacy'), __('Every church’s data is isolated and every gift is receipted.')],
                            ['bi-people', __('Partnership'), __('Continuous support from a team that knows your context.')],
                        ] as [$icon, $t, $d])
                            <div class="col-sm-6">
                                <div class="ff-card h-100">
                                    <i class="bi {{ $icon }} fs-3 text-ff-primary"></i>
                                    <h3 class="h6 mt-2">{{ $t }}</h3>
                                    <p class="small text-ff-muted mb-0">{{ $d }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ff-section">
        <div class="container">
            <x-ff.section-title :eyebrow="__('The team')" :title="__('The people behind FaithFellows')" />
            <div class="row g-4 justify-content-center">
                @foreach (config('faithfellows.team') as $member)
                    <div class="col-sm-6 col-lg-4">
                        <div class="ff-card text-center h-100 p-4">
                            <span class="ff-avatar ff-avatar-xl mx-auto mb-3">{{ ff_initials($member['name']) }}</span>
                            <h3 class="h5 mb-1">{{ $member['name'] }}</h3>
                            <p class="text-ff-primary fw-semibold small mb-2">{{ $member['role'] }}</p>
                            @if ($member['phone'])
                                <a href="tel:{{ $member['phone'] }}" class="small text-ff-muted"><i class="bi bi-telephone"></i> {{ $member['phone'] }}</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ff-section pt-0">
        <div class="container">
            <div class="ff-card ff-card-mist p-4 p-md-5">
                <div class="row g-4 align-items-center">
                    <div class="col-md-8">
                        <h2 class="h4">{{ config('faithfellows.company.name') }}</h2>
                        <p class="mb-0 text-ff-black">{{ config('faithfellows.company.address') }} · {{ config('faithfellows.company.po_box') }}<br>
                            <i class="bi bi-telephone"></i> {{ config('faithfellows.company.phone') }} · <i class="bi bi-envelope"></i> {{ config('faithfellows.company.email') }}</p>
                    </div>
                    <div class="col-md-4 text-md-end"><a href="{{ route('contact') }}" class="btn btn-ff-primary px-4">{{ __('Talk to us') }}</a></div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
