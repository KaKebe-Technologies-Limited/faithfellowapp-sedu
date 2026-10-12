{{-- Compact event row. Expects $event (church loaded). --}}
<a href="{{ route('events.show', $event) }}" class="ff-card ff-card-sm ff-card-link ff-card-hover d-flex align-items-center gap-3">
    <div class="ff-date-tile"><div class="m">{{ $event->starts_at->format('M') }}</div><div class="d">{{ $event->starts_at->format('j') }}</div></div>
    <div class="min-w-0 flex-grow-1">
        <div class="fw-semibold text-ff-black text-truncate">{{ $event->title }}</div>
        <div class="small text-ff-muted text-truncate">
            {{ $event->starts_at->format('D, g:i A') }}
            @if ($event->is_online)· <i class="bi bi-camera-video"></i> {{ __('Online') }}@elseif ($event->location_name)· {{ $event->location_name }}@endif
            · {{ $event->church->name }}
        </div>
    </div>
    @if ($event->is_paid)<span class="ff-badge ff-badge-warning">{{ __('Tickets') }}</span>@elseif ($event->rsvp_enabled)<span class="ff-badge ff-badge-primary">{{ __('RSVP') }}</span>@endif
    @isset($myRsvps[$event->id])<span class="ff-badge ff-badge-success"><i class="bi bi-check2"></i> {{ __(Str::headline($myRsvps[$event->id])) }}</span>@endisset
</a>
