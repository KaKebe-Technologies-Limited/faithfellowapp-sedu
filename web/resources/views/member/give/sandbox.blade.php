<x-layouts.auth :title="__('Test card checkout')">
    <div class="text-center">
        <span class="ff-badge ff-badge-warning mb-3">{{ __('SANDBOX — no real money') }}</span>
        <h1 class="h4">{{ __('Card checkout') }}</h1>
        <p class="text-ff-muted">{{ $donation->church->name }} · {{ $donation->category_label }}</p>
        <div class="display-6 fw-bold text-ff-black mb-4">{{ ff_money($donation->amount, $donation->currency) }}</div>
        <div class="ff-card ff-card-gray ff-card-sm text-start mb-4 small">
            <div><i class="bi bi-credit-card"></i> 4242 4242 4242 4242 · 12/34 · 123</div>
            <div class="text-ff-muted">{{ __('In production this page is Stripe Checkout.') }}</div>
        </div>
        <form method="POST" class="d-grid gap-2">@csrf
            <button name="decision" value="approve" class="btn btn-ff-primary btn-lg">{{ __('Pay :amount', ['amount' => ff_money($donation->amount)]) }}</button>
            <button name="decision" value="decline" class="btn btn-ff-ghost">{{ __('Decline payment') }}</button>
        </form>
    </div>
</x-layouts.auth>
