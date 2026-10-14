<x-layouts.member :title="__('Payment status')">
    <div class="mx-auto text-center" style="max-width: 520px"
         x-data="paymentStatus({ url: @js(route('give.status.check', $donation->reference)), status: @js($donation->status) })">
        <div class="ff-card p-4 p-md-5">
            <x-ff.church-avatar :church="$donation->church" size="lg" class="mx-auto mb-3" />
            <div class="small text-ff-muted">{{ $donation->church->name }} · {{ $donation->category_label }}@if ($donation->campaign) · {{ $donation->campaign->title }}@endif</div>
            <div class="display-6 fw-bold text-ff-black my-2">{{ ff_money($donation->amount, $donation->currency) }}</div>

            <template x-if="status === 'pending' || status === 'processing'">
                <div>
                    <div class="spinner-border text-primary my-3" role="status"><span class="visually-hidden">{{ __('Waiting') }}</span></div>
                    @if ($donation->method === 'bank')
                        <p class="fw-semibold">{{ __('Complete your bank transfer') }}</p>
                        <p class="small text-ff-muted">{{ __('Use this reference so we can match your gift:') }} <strong class="text-ff-black">{{ $donation->reference }}</strong></p>
                    @else
                        <p class="fw-semibold mb-1"><i class="bi bi-phone-vibrate"></i> {{ __('Check your phone') }}</p>
                        <p class="small text-ff-muted">{{ __('Approve the :method prompt on :phone and enter your PIN. This page updates automatically.', ['method' => $donation->method_label, 'phone' => $donation->phone]) }}</p>
                    @endif
                </div>
            </template>
            <template x-if="status === 'succeeded'">
                <div>
                    <div class="text-success" style="font-size: 4rem"><i class="bi bi-check-circle-fill"></i></div>
                    <h1 class="h4">{{ __('Thank you for your gift!') }}</h1>
                    <p class="text-ff-muted">{{ __('Your receipt has been sent to your email and notifications.') }}</p>
                    <a href="{{ route('giving.receipt', $donation->reference) }}" class="btn btn-ff-outline"><i class="bi bi-file-earmark-pdf"></i> {{ __('Download receipt') }}</a>
                </div>
            </template>
            <template x-if="status === 'failed' || status === 'cancelled'">
                <div>
                    <div class="text-danger" style="font-size: 4rem"><i class="bi bi-x-circle-fill"></i></div>
                    <h1 class="h5">{{ __('The payment did not go through') }}</h1>
                    <p class="text-ff-muted small" x-text="message || '{{ $donation->failure_reason ?? __('Please try again.') }}'"></p>
                    <a href="{{ route('give.create', $donation->church) }}" class="btn btn-ff-primary">{{ __('Try again') }}</a>
                </div>
            </template>
            <template x-if="status === 'timeout'">
                <p class="text-ff-muted small">{{ __('We have not heard back yet. If you approved the payment, it will show in your giving history shortly.') }}</p>
            </template>

            <div class="small text-ff-muted mt-4">{{ __('Reference') }}: {{ $donation->reference }}</div>
        </div>
        <div class="mt-3 d-flex gap-2 justify-content-center">
            <a href="{{ route('giving.index') }}" class="btn btn-ff-ghost">{{ __('My giving') }}</a>
            @if ($donation->campaign)<a href="{{ route('campaigns.show', $donation->campaign) }}" class="btn btn-ff-ghost">{{ __('Back to campaign') }}</a>@endif
        </div>
    </div>
</x-layouts.member>
