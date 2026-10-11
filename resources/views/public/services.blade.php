<x-layouts.public :title="__('Services')" :description="__('Websites, sermons, live streaming, mobile money giving, campaigns, events, prayer, small groups and member care — all in one platform.')">
    <section class="ff-page-hero">
        <div class="container">
            <span class="ff-eyebrow">{{ __('Platform capabilities') }}</span>
            <h1 class="ff-display mt-3 mb-3" style="max-width: 860px">{{ __('A complete digital back office for your church') }}</h1>
            <p class="ff-lead mb-0" style="max-width: 720px">{{ __('Everything below is included in every FaithFellows plan — no add-ons required to run a modern, connected ministry.') }}</p>
        </div>
    </section>

    @php
        $groups = [
            [
                'eyebrow' => __('Presence'), 'title' => __('Your church, online in a day'), 'icon' => 'bi-globe2',
                'text' => __('An auto-generated, mobile-first website with drag-and-drop sections, your own subdomain and optional custom domain.'),
                'items' => [__('yourchurch.faithfellows.app subdomain'), __('Custom domain add-on'), __('Hero, sermons, events, giving, campaigns & prayer blocks'), __('Google listing & SEO'), __('2× weekly social media posts by our team')],
            ],
            [
                'eyebrow' => __('Content'), 'title' => __('Sermons, devotionals & live services'), 'icon' => 'bi-broadcast',
                'text' => __('Publish and stream the Word wherever your people are — even on slow networks.'),
                'items' => [__('Audio & video sermons with notes, series and playlists'), __('Daily devotionals and Bible reading plans with streaks'), __('Live streaming with audio-only data-saver mode'), __('Recordings archived as sermons automatically'), __('Offline downloads for members')],
            ],
            [
                'eyebrow' => __('Giving'), 'title' => __('Generosity made simple'), 'icon' => 'bi-phone-vibrate',
                'text' => __('Receive tithes, offerings and project gifts through the channels Ugandans actually use.'),
                'items' => [__('MTN MoMo, Airtel Money, Iotec, card (Stripe), bank, USSD & cash'), __('Recurring giving and pledges with reminders'), __('Instant PDF receipts by email, in-app and SMS'), __('Finance dashboard, exports and reconciliation'), __('Campaigns with progress, milestones, teams and QR codes')],
            ],
            [
                'eyebrow' => __('Community'), 'title' => __('Connection all week long'), 'icon' => 'bi-people',
                'text' => __('Keep your congregation engaged between Sundays.'),
                'items' => [__('Prayer wall with privacy levels and prayer chains'), __('Testimonies, comments and reactions with moderation'), __('Small groups / cells with attendance and maps'), __('Events with RSVPs, tickets and QR check-in'), __('Volunteer teams and rotas')],
            ],
            [
                'eyebrow' => __('Pastoral care'), 'title' => __('Shepherd every member'), 'icon' => 'bi-heart-pulse',
                'text' => __('Confidential tools that help leaders care well.'),
                'items' => [__('Visitor → member → leader follow-up workflow'), __('Birthday & anniversary reminders'), __('Private pastoral notes and welfare tracking'), __('Home visit scheduling'), __('Counseling appointment booking')],
            ],
            [
                'eyebrow' => __('Administration'), 'title' => __('Run your church with confidence'), 'icon' => 'bi-shield-check',
                'text' => __('Delegate safely and see what is working.'),
                'items' => [__('Staff roles: admin, editor, finance, media, events, moderator & custom'), __('Multi-branch support with consolidated reports'), __('Analytics for growth, engagement and giving'), __('Audit logs of every admin action'), __('Continuous tech support from Kakebe Technologies')],
            ],
        ];
    @endphp

    <section class="ff-section">
        <div class="container d-grid gap-5">
            @foreach ($groups as $i => $g)
                <div class="row g-4 align-items-center {{ $i % 2 ? 'flex-lg-row-reverse' : '' }}">
                    <div class="col-lg-6">
                        <span class="ff-eyebrow">{{ $g['eyebrow'] }}</span>
                        <h2 class="mt-2">{{ $g['title'] }}</h2>
                        <p class="ff-lead">{{ $g['text'] }}</p>
                        <ul class="ff-check-list">
                            @foreach ($g['items'] as $item)<li>{{ $item }}</li>@endforeach
                        </ul>
                    </div>
                    <div class="col-lg-6">
                        <div @class(['ff-card p-5 text-center', 'bg-ff-gradient border-0' => $i % 2 === 0, 'ff-card-mist' => $i % 2 === 1])>
                            <i class="bi {{ $g['icon'] }}" style="font-size: 5rem; {{ $i % 2 === 0 ? 'color:#fff' : 'color:var(--ff-primary)' }}"></i>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="ff-section pt-0">
        <div class="container">
            <div class="ff-cta-band text-center">
                <h2 class="mb-3 position-relative">{{ __('All of this for UGX 60,000/month') }}</h2>
                <a href="{{ route('pricing') }}" class="btn btn-ff-white btn-lg px-4 position-relative">{{ __('See pricing') }}</a>
            </div>
        </div>
    </section>
</x-layouts.public>
