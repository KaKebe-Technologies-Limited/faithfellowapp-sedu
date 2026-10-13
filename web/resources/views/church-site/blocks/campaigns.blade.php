@php($campaigns = ($sections['campaigns'] ?? collect())->where('visibility', 'public')->take(3))
@if ($campaigns->isNotEmpty())
    <section class="ff-section site-section" id="campaigns">
        <div class="container">
            <div class="text-center mb-5"><span class="ff-eyebrow site-accent">{{ __('Partner with us') }}</span><h2 class="mt-2">{{ __('Current projects') }}</h2></div>
            <div class="row g-4 justify-content-center">
                @foreach ($campaigns as $campaign)
                    <div class="col-md-4">
                        <a href="{{ ff_platform_route('campaigns.show', $campaign) }}" class="ff-card ff-card-flush ff-card-hover ff-card-link h-100 d-block">
                            @if ($campaign->picture_url)<img src="{{ $campaign->picture_url }}" alt="" class="ff-cover" loading="lazy">@endif
                            <div class="p-4">
                                <div class="fw-bold text-ff-black mb-2">{{ $campaign->title }}</div>
                                <div class="ff-progress mb-2"><div class="ff-progress-bar" style="width: {{ min(100, $campaign->percent()) }}%; background: var(--site-accent)"></div></div>
                                <div class="small">{{ ff_money($campaign->raised_amount) }} {{ __('of :g', ['g' => ff_money($campaign->goal_amount)]) }}</div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
