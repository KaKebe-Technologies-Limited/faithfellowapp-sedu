<x-layouts.church-admin :church="$church" :title="__('Check-in')">
    <div class="mx-auto text-center" style="max-width: 520px">
        <h1 class="h4 mb-1">{{ $event->title }}</h1>
        <p class="text-ff-muted">{{ __(':in of :expected checked in', ['in' => $checkedIn, 'expected' => $expected]) }}</p>
        <x-ff.progress :value="$expected ? $checkedIn / $expected * 100 : 0" class="mb-4" />

        @if ($result)
            <div @class(['ff-card mb-4 border-0', 'bg-success text-white' => $result['ok'], 'bg-danger text-white' => ! $result['ok']]) role="status">
                <i class="bi {{ $result['ok'] ? 'bi-check-circle' : 'bi-x-circle' }} fs-1"></i>
                <div class="fs-5 fw-bold mt-2">{{ $result['message'] }}</div>
            </div>
        @endif

        <form method="POST" action="{{ route('church-admin.events.check-in.store', [$church, $event]) }}" class="ff-card">@csrf
            <label for="code" class="form-label">{{ __('Ticket code') }}</label>
            <input id="code" name="code" class="form-control form-control-lg text-center fw-bold mb-3" style="letter-spacing:.2em; text-transform: uppercase" autofocus autocomplete="off" required>
            <button class="btn btn-ff-primary btn-lg w-100">{{ __('Check in') }}</button>
            <p class="small text-ff-muted mt-3 mb-0"><i class="bi bi-phone"></i> {{ __('Tip: scan a member’s ticket QR with your phone camera while signed in — it checks them in automatically.') }}</p>
        </form>
        <a href="{{ route('church-admin.events.show', [$church, $event]) }}" class="btn btn-ff-ghost mt-3">{{ __('Back to attendees') }}</a>
    </div>
</x-layouts.church-admin>
