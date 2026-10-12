<x-layouts.member :title="__('Events')">
    <x-ff.page-header :title="__('Events')" :subtitle="__('Upcoming events at your churches.')" />

    @if ($myTickets->isNotEmpty())
        <h2 class="h6 text-uppercase text-ff-muted mb-3">{{ __('My tickets') }}</h2>
        <div class="row g-3 mb-4">
            @foreach ($myTickets as $rsvp)
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('events.ticket', $rsvp->event) }}" class="ff-card ff-card-sm ff-card-link bg-ff-gradient border-0 d-flex align-items-center gap-3 text-white">
                        <i class="bi bi-qr-code fs-2"></i>
                        <span class="min-w-0"><span class="d-block fw-bold text-truncate">{{ $rsvp->event->title }}</span><span class="small" style="opacity:.9">{{ $rsvp->event->starts_at->format('D j M, g:i A') }}</span></span>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    @if ($events->isEmpty())
        <x-ff.empty-state icon="bi-calendar-event" :title="__('No upcoming events')" :message="__('When your churches post events, they will appear here.')" />
    @else
        <div class="d-grid gap-3" style="max-width: 900px">
            @foreach ($events as $event)
                @include('member.events._row')
            @endforeach
        </div>
        <div class="mt-4">{{ $events->links() }}</div>
    @endif
</x-layouts.member>
