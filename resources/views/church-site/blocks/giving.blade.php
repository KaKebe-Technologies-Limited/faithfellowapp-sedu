@php($channels = $sections['givingChannels'] ?? collect())
@if ($channels->isNotEmpty())
    <section class="ff-section site-section" id="give">
        <div class="container text-center" style="max-width: 760px">
            <span class="ff-eyebrow site-accent">{{ __('Give') }}</span>
            <h2 class="mt-2">{{ __('Support the ministry') }}</h2>
            <p class="ff-lead">{{ __('Give your tithes and offerings securely with mobile money or card. You receive an instant receipt.') }}</p>
            <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                @foreach ($channels as $channel)<span class="ff-badge">{{ $channel->display_name }}</span>@endforeach
            </div>
            <a href="{{ ff_platform_route('give.create', $church) }}" class="btn site-btn btn-lg px-5"><i class="bi bi-heart-fill"></i> {{ __('Give online') }}</a>
            @if ($bank = $channels->firstWhere('type', 'bank'))
                <div class="ff-card ff-card-gray mt-4 text-start small">
                    <strong>{{ __('Bank transfer') }}</strong>
                    @foreach ($bank->details ?? [] as $label => $value)<div>{{ Str::headline($label) }}: {{ $value }}</div>@endforeach
                </div>
            @endif
        </div>
    </section>
@endif
