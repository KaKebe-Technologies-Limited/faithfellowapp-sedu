<x-layouts.public :title="__('Pricing')" :description="__('UGX 60,000/month — everything included, no surprises.')">
    @php
        $pricing  = config('faithfellows.pricing');
        $applyUrl = Route::has('church.apply') ? route('church.apply') : route('contact');
        $faqs = [
            __('Is hosting really free for the first year?')  => __('Yes — your website and content hosting are free for 12 months.'),
            __('Do I need my own domain?')                    => __('No. You get yourchurch.faithfellows.com free. A custom domain is UGX 60,000/year.'),
            __('How do members pay?')                         => __('MTN MoMo, Airtel Money, Iotec, card, bank, USSD or cash. Your choice.'),
            __('Do you take a cut of donations?')             => __('Never. Only the payment provider\'s standard fee applies.'),
            __('Can members follow more than one church?')    => __('Yes — one account, unlimited churches.'),
            __('Who owns our data?')                          => __('You do. Export anytime. We never share it.'),
            __('What if we miss a payment?')                  => __(':days-day grace period with reminders. Nothing is deleted.', ['days' => $pricing['grace_days']]),
        ];
    @endphp

    <section class="ff-page-hero text-center">
        <div class="container">
            <span class="ff-eyebrow">{{ __('Pricing') }}</span>
            <h1 class="ff-display mt-3 mb-3">{{ __('Simple, honest pricing.') }}</h1>
            <p class="ff-lead mx-auto mb-0" style="max-width:500px">{{ __('One plan. Everything included. No surprises.') }}</p>
        </div>
    </section>

    <section class="ff-section pt-4">
        <div class="container">
            <div class="row justify-content-center g-4">
                <div class="col-lg-5">
                    <div class="ff-price-card h-100">
                        <div class="ff-price-card-badge">{{ __('Starter — most churches') }}</div>
                        <div class="ff-price-card-amount">
                            <span class="ff-price-currency">UGX</span>
                            <span class="ff-price-num">{{ number_format($pricing['plan_price']) }}</span>
                            <span class="ff-price-period">/{{ __('mo') }}</span>
                        </div>
                        <ul class="ff-bullet-list text-start mb-4">
                            @foreach ($pricing['includes'] as $item)
                                <li><i class="bi bi-check-circle-fill text-ff-primary"></i> {{ $item }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ $applyUrl }}" class="btn btn-ff-primary btn-lg w-100">{{ __('Register your church') }}</a>
                        <div class="ff-price-card-footer">
                            <i class="bi bi-lock-fill text-ff-primary"></i> {{ __('No setup fee · Cancel anytime') }}
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="ff-card ff-card-gray h-100 p-4">
                        <h2 class="h5 mb-3">{{ __('Add-ons') }}</h2>
                        <div class="d-flex justify-content-between py-3 border-bottom">
                            <span class="small">{{ __('Custom domain') }}</span>
                            <strong class="text-ff-black small">UGX {{ number_format($pricing['domain_price']) }}/{{ __('yr') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between py-3 border-bottom">
                            <span class="small">{{ __('Extra storage & streams') }}</span>
                            <strong class="text-ff-black small">{{ __('On request') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between py-3">
                            <span class="small">{{ __('Diocese / network plan') }}</span>
                            <strong class="text-ff-black small">{{ __('Contact us') }}</strong>
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('contact') }}" class="btn btn-ff-outline w-100">{{ __('Talk to us') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ff-section bg-ff-gray">
        <div class="container" style="max-width:800px">
            <x-ff.section-title :eyebrow="__('FAQ')" :title="__('Common questions')" />
            <div class="accordion mt-4" id="pricingFaq">
                @foreach ($faqs as $q => $a)
                    <div class="accordion-item border-0 mb-3 rounded-4 overflow-hidden shadow-sm">
                        <h3 class="accordion-header">
                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }} fw-semibold"
                                    type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq{{ $loop->index }}"
                                    aria-expanded="{{ $loop->first ? 'true' : 'false' }}">{{ $q }}</button>
                        </h3>
                        <div id="faq{{ $loop->index }}"
                             class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                             data-bs-parent="#pricingFaq">
                            <div class="accordion-body text-ff-muted small">{{ $a }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

</x-layouts.public>
