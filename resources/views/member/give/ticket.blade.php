<x-layouts.member :title="__('Pay for tickets')">
    <div class="mx-auto" style="max-width: 560px">
        <div class="ff-card p-4">
            <h1 class="h5">{{ $rsvp->event->title }}</h1>
            <p class="text-ff-muted small">{{ $rsvp->event->starts_at->format('l j F, g:i A') }} · {{ $rsvp->quantity }} × {{ $rsvp->ticket?->name }}</p>
            <div class="fs-3 fw-bold text-ff-black mb-4">{{ ff_money($rsvp->amount) }}</div>
            @if ($channels->isEmpty())
                <div class="alert alert-warning">{{ __('Online payment is not available for this church yet.') }}</div>
            @else
                <form method="POST" x-data="givingForm({ amount: {{ (float) $rsvp->amount }}, method: '{{ $channels->first()->type }}' })">@csrf
                    <div class="d-grid gap-2 mb-3">
                        @foreach ($channels as $channel)
                            <label class="ff-method-option"><input type="radio" class="form-check-input mt-0" name="method" value="{{ $channel->type }}" x-model="method"><strong class="text-ff-black">{{ $channel->display_name }}</strong></label>
                        @endforeach
                    </div>
                    <div x-show="needsPhone" x-cloak><x-ff.input name="phone" type="tel" :label="__('Mobile money number')" :value="auth()->user()->phone" /></div>
                    <button class="btn btn-ff-primary w-100 btn-lg">{{ __('Pay now') }}</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.member>
