<x-layouts.auth :title="__('Two-factor verification')">
    <div class="text-center">
        <div class="ff-feature-icon mx-auto"><i class="bi bi-shield-lock"></i></div>
        <h1 class="mb-2">{{ __('Enter your code') }}</h1>
        <p class="text-ff-muted">{{ __('We emailed you a 6-digit code. It expires in 10 minutes.') }}</p>
    </div>

    <form method="POST" action="{{ route('two-factor.challenge') }}" novalidate>
        @csrf
        <x-ff.input name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" class="form-control-lg text-center fw-bold" style="letter-spacing:.5em" required autofocus />
        <button class="btn btn-ff-primary w-100 py-2">{{ __('Verify & continue') }}</button>
    </form>
    <p class="text-center small mt-4 mb-0"><a href="{{ route('login') }}">{{ __('Start over') }}</a></p>
</x-layouts.auth>
