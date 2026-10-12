<x-layouts.member :title="__('Ticket')">
    <div class="mx-auto" style="max-width: 420px">
        <div class="ff-card ff-card-flush text-center">
            <div class="bg-ff-gradient p-4">
                <x-ff.church-avatar :church="$event->church" size="lg" class="ff-avatar-ring mb-2" />
                <div class="fw-bold fs-5 text-white">{{ $event->title }}</div>
                <div class="small text-white" style="opacity:.9">{{ $event->starts_at->format('l, j F Y · g:i A') }}</div>
            </div>
            <div class="p-4">
                <div class="ff-qr mb-3" style="max-width: 260px">{!! $qr !!}</div>
                <div class="fs-4 fw-bold text-ff-black" style="letter-spacing:.15em">{{ $rsvp->code }}</div>
                <div class="small text-ff-muted mb-3">{{ auth()->user()->name }} · {{ trans_choice(':count guest|:count guests', $rsvp->quantity) }}@if ($rsvp->ticket) · {{ $rsvp->ticket->name }}@endif</div>
                @if ($rsvp->checked_in_at)
                    <x-ff.status status="checked_in">{{ __('Checked in at :t', ['t' => $rsvp->checked_in_at->format('g:i A')]) }}</x-ff.status>
                @else
                    <p class="small text-ff-muted mb-0">{{ __('Show this code at the door. Tip: take a screenshot — it works without data.') }}</p>
                @endif
            </div>
        </div>
        <div class="text-center mt-3">
            <button onclick="window.print()" class="btn btn-ff-ghost btn-sm"><i class="bi bi-printer"></i> {{ __('Print') }}</button>
            <a href="{{ route('events.show', $event) }}" class="btn btn-ff-ghost btn-sm">{{ __('Event details') }}</a>
        </div>
    </div>
</x-layouts.member>
