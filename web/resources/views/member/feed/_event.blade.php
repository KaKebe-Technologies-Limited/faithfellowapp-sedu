<article class="ff-card ff-feed-item">
    <div class="ff-feed-meta">
        <x-ff.church-avatar :church="$event->church" size="sm" />
        <div class="min-w-0">
            <a href="{{ route('churches.show', $event->church) }}" class="fw-semibold text-ff-black">{{ $event->church->name }}</a>
            <div class="small-xs text-ff-muted">{{ __('posted an event') }} · {{ $event->created_at->diffForHumans() }}</div>
        </div>
        <span class="ff-feed-type ff-tag"><i class="bi bi-calendar-event"></i> {{ __('Event') }}</span>
    </div>
    @if ($event->cover_url)<img src="{{ $event->cover_url }}" alt="" class="ff-cover rounded-3" loading="lazy">@endif
    <a href="{{ route('events.show', $event) }}" class="d-flex gap-3 align-items-center ff-card-link">
        <div class="ff-date-tile"><div class="m">{{ $event->starts_at->format('M') }}</div><div class="d">{{ $event->starts_at->format('j') }}</div></div>
        <div class="min-w-0"><div class="fw-bold text-ff-black">{{ $event->title }}</div><div class="small text-ff-muted">{{ $event->starts_at->format('D, g:i A') }}@if ($event->location_name) · {{ $event->location_name }}@endif</div></div>
        <span class="btn btn-sm btn-ff-soft ms-auto">{{ $event->is_paid ? __('Tickets') : __('RSVP') }}</span>
    </a>
</article>
