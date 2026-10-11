<x-layouts.public :title="__('Pricing')" :description="__('FaithFellows Starter plan: UGX 60,000/month with free hosting for the first year.')">
    @php
        $pricing = config('faithfellows.pricing');
        $applyUrl = Route::has('church.apply') ? route('church.apply') : route('contact');
        $faqs = [
            __('Is there really free hosting for the first year?') => __('Yes. Your church website and content hosting are free for the first 12 months. The monthly plan covers the platform, support, giving integration and social media posts.'),
            __('Do I need my own domain?') => __('No. Every church gets a free subdomain like yourchurch.faithfellows.app. If you want www.yourchurch.org we can register and connect it for UGX 60,000/year.'),
            __('How do church members pay?') => __('Members give with MTN MoMo, Airtel Money, Iotec, card (great for diaspora), bank transfer, USSD on feature phones, or cash recorded by your finance team.'),
            __('Do you charge a fee on donations?') => __('FaithFellows does not take a cut of your gifts. Payment providers charge their standard transaction fees.'),
            __('Can members follow more than one church?') => __('Yes — one account, many churches. Members subscribe and unsubscribe whenever they like.'),
            __('Who owns our church data?') => __('You do. Your data is isolated from other churches and you can export members, giving and reports at any time.'),
            __('What happens if we miss a payment?') => __('You get a :days-day grace period with reminders before the account is paused. Nothing is deleted.', ['days' => $pricing['grace_days']]),
        ];
    @endphp

    <section class="ff-page-hero text-center">
        <div class="container">
            <span class="ff-eyebrow">{{ __('Pricing') }}</span>
            <h1 class="ff-display mt-3 mb-3">{{ __('Simple, honest pricing') }}</h1>
            <p class="ff-lead mx-auto mb-0" style="max-width: 620px">{{ __('One plan with everything included. No setup fees, no per-member charges, no surprises.') }}</p>
        </div>
    </section>

    <section class="ff-section pt-4">
        <div class="container">
            <div class="row justify-content-center g-4">
                <div class="col-lg-6">
                    <div class="ff-card p-4 p-md-5 ff-shadow-primary h-100" style="border: 2px solid var(--ff-primary)">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="h4 mb-0">{{ __('Starter') }}</h2>
                            <span class="ff-badge ff-badge-primary">{{ __('Most churches') }}</span>
                        </div>
                        <div class="my-4"><span class="fs-5 fw-semibold text-ff-muted">UGX</span> <span class="ff-display">{{ number_format($pricing['plan_price']) }}</span><span class="text-ff-muted">/{{ __('month') }}</span></div>
                        <ul class="ff-check-list mb-4">
                            @foreach ($pricing['includes'] as $item)<li>{{ $item }}</li>@endforeach
                        </ul>
                        <a href="{{ $applyUrl }}" class="btn btn-ff-primary btn-lg w-100">{{ __('Register your church') }}</a>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="ff-card ff-card-gray h-100 p-4">
                        <h2 class="h5">{{ __('Add-ons') }}</h2>
                        <div class="d-flex justify-content-between py-3 border-bottom"><span>{{ __('Custom domain (.org, .com, .ug)') }}</span><strong class="text-ff-black">UGX {{ number_format($pricing['domain_price']) }}/{{ __('yr') }}</strong></div>
                        <div class="d-flex justify-content-between py-3 border-bottom"><span>{{ __('Extra storage & stream hours') }}</span><strong class="text-ff-black">{{ __('On request') }}</strong></div>
                        <div class="d-flex justify-content-between py-3"><span>{{ __('Multi-branch networks') }}</span><strong class="text-ff-black">{{ __('Contact us') }}</strong></div>
                        <hr>
                        <p class="small text-ff-muted mb-3">{{ __('Need a tailored plan for a diocese or ministry network?') }}</p>
                        <a href="{{ route('contact') }}" class="btn btn-ff-outline w-100">{{ __('Talk to sales') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ff-section bg-ff-gray">
        <div class="container" style="max-width: 860px">
            <x-ff.section-title :eyebrow="__('FAQ')" :title="__('Frequently asked questions')" />
            <div class="accordion" id="pricingFaq">
                @foreach ($faqs as $q => $a)
                    <div class="accordion-item border-0 mb-3 rounded-4 overflow-hidden shadow-sm">
                        <h3 class="accordion-header">
                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }} fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $loop->index }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">{{ $q }}</button>
                        </h3>
                        <div id="faq{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#pricingFaq">
                            <div class="accordion-body text-ff-muted">{{ $a }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.public>
