<x-layouts.public :title="__('Services')" :description="__('Everything your church needs — website, giving, sermons, events, care and more.')">

    <section class="ff-page-hero">
        <div class="container">
            <span class="ff-eyebrow">{{ __('What\'s included') }}</span>
            <h1 class="ff-display mt-3 mb-3">{{ __('Everything your church needs.') }}</h1>
            <p class="ff-lead mb-0" style="max-width:580px">{{ __('One plan. No add-ons required.') }}</p>
        </div>
    </section>

    @php
        $groups = [
            [
                'eyebrow' => __('Presence'),
                'title'   => __('Your church website — live in a day.'),
                'icon'    => 'bi-globe2',
                'items'   => [__('Free subdomain: yourchurch.faithfellows.app'), __('Drag-and-drop page blocks'), __('Google listing & SEO'), __('Custom domain (add-on)')],
            ],
            [
                'eyebrow' => __('Content'),
                'title'   => __('Sermons, devotionals & live streaming.'),
                'icon'    => 'bi-broadcast',
                'items'   => [__('Audio & video with series and playlists'), __('Daily devotionals & reading plans'), __('Live stream with audio-only mode'), __('Offline downloads for members')],
            ],
            [
                'eyebrow' => __('Giving'),
                'title'   => __('Receive gifts the way Ugandans give.'),
                'icon'    => 'bi-phone-vibrate',
                'items'   => [__('MTN MoMo, Airtel, Iotec, Stripe, USSD & cash'), __('Recurring giving & pledges'), __('Instant PDF receipts'), __('Finance dashboard & exports')],
            ],
            [
                'eyebrow' => __('Community'),
                'title'   => __('Connected every day of the week.'),
                'icon'    => 'bi-people',
                'items'   => [__('Prayer wall with privacy levels'), __('Events, RSVPs & QR check-in'), __('Small groups with attendance'), __('Testimonies, comments & reactions')],
            ],
            [
                'eyebrow' => __('Pastoral care'),
                'title'   => __('Shepherd every member.'),
                'icon'    => 'bi-heart-pulse',
                'items'   => [__('Visitor → member → leader workflow'), __('Birthday & anniversary alerts'), __('Private pastoral notes'), __('Counseling booking')],
            ],
            [
                'eyebrow' => __('Administration'),
                'title'   => __('Run your church with confidence.'),
                'icon'    => 'bi-shield-check',
                'items'   => [__('Custom staff roles & permissions'), __('Multi-branch support'), __('Analytics & audit logs'), __('Kakebe tech support')],
            ],
        ];
    @endphp

    <section class="ff-section">
        <div class="container d-grid gap-5">
            @foreach ($groups as $i => $g)
                <div class="row g-4 align-items-center {{ $i % 2 ? 'flex-lg-row-reverse' : '' }}">
                    <div class="col-lg-6">
                        <span class="ff-eyebrow">{{ $g['eyebrow'] }}</span>
                        <h2 class="mt-2 mb-3">{{ $g['title'] }}</h2>
                        <ul class="ff-bullet-list">
                            @foreach ($g['items'] as $item)
                                <li><i class="bi bi-check-circle-fill text-ff-primary"></i> {{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-lg-6">
                        <div @class(['ff-card p-5 text-center', 'bg-ff-gradient border-0' => $i % 2 === 0, 'ff-card-mist' => $i % 2 === 1])>
                            <i class="bi {{ $g['icon'] }}" style="font-size:5rem;{{ $i % 2 === 0 ? 'color:#fff' : 'color:var(--ff-primary)' }}"></i>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="ff-section pt-0">
        <div class="container">
            <div class="ff-cta-v2 text-center">
                <h2 class="text-white mb-3 position-relative" style="z-index:1">{{ __('All of this for UGX 60,000/month.') }}</h2>
                <a href="{{ route('pricing') }}" class="btn btn-ff-white btn-lg px-5 position-relative" style="z-index:1">{{ __('See pricing') }}</a>
                <div class="ff-cta-v2-glow"></div>
            </div>
        </div>
    </section>

</x-layouts.public>
