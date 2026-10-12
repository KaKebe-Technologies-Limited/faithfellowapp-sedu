@php($events = ($sections['events'] ?? collect())->where('visibility', 'public')->take(3))
@if ($events->isNotEmpty())
    <section class="ff-section site-section" id="events">
        <div class="container">
            <div class="text-center mb-5"><span class="ff-eyebrow site-accent">{{ __('Join us') }}</span><h2 class="mt-2">{{ __('Upcoming events') }}</h2></div>
            <div class="row g-4">
                @foreach ($events as $event)
                    <div class="col-md-4">
                        <a href="{{ ff_platform_route('events.show', $event) }}" class="ff-card ff-card-hover ff-card-link h-100 d-flex gap-3">
                            <div class="ff-date-tile"><div class="m">{{ $event->starts_at->format('M') }}</div><div class="d">{{ $event->starts_at->format('j') }}</div></div>
                            <div><div class="fw-bold text-ff-black">{{ $event->title }}</div><div class="small text-ff-muted">{{ $event->starts_at->format('D g:i A') }}@if ($event->location_name) · {{ $event->location_name }}@endif</div></div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
