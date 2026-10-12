@if ($church->serviceTimes->isNotEmpty())
    <section class="ff-section site-section" id="services">
        <div class="container">
            <div class="text-center mb-5">
                <span class="ff-eyebrow site-accent">{{ __('Worship with us') }}</span>
                <h2 class="mt-2">{{ __('Service times') }}</h2>
            </div>
            <div class="row g-3 justify-content-center">
                @foreach ($church->serviceTimes as $service)
                    <div class="col-sm-6 col-lg-4">
                        <div class="ff-card h-100 text-center">
                            <div class="small fw-bold text-uppercase site-accent">{{ $service->day_name }}</div>
                            <div class="fs-4 fw-bold text-ff-black my-1">{{ $service->time_range }}</div>
                            <div class="fw-semibold">{{ $service->name }}</div>
                            <div class="small text-ff-muted">{{ config('faithfellows.languages.'.$service->language, $service->language) }}@if ($service->location) · {{ $service->location }}@endif</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
