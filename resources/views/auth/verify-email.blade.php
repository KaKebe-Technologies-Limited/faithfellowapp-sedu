<x-layouts.auth :title="__('Verify your email')">
    <div class="text-center">
        <div class="ff-feature-icon mx-auto"><i class="bi bi-envelope-check"></i></div>
        <h1 class="mb-2">{{ __('Check your inbox') }}</h1>
        <p class="text-ff-muted">{{ __('We sent a verification link to :email. Click it to activate your account.', ['email' => auth()->user()->email]) }}</p>
    </div>

    @if (session('status') === 'verification-link-sent')
        <div class="alert alert-success small">{{ __('A new verification link has been sent.') }}</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mb-2">
        @csrf
        <button class="btn btn-ff-primary w-100">{{ __('Resend verification email') }}</button>
    </form>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn btn-ff-ghost w-100">{{ __('Log out') }}</button>
    </form>
</x-layouts.auth>
