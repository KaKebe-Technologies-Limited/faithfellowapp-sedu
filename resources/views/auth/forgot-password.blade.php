<x-layouts.auth :title="__('Forgot password')">
    <h1 class="mb-1">{{ __('Reset your password') }}</h1>
    <p class="text-ff-muted mb-4">{{ __('Enter your email and we will send you a link to choose a new password.') }}</p>

    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <x-ff.input name="email" type="email" :label="__('Email')" required autofocus />
        <button class="btn btn-ff-primary w-100 py-2">{{ __('Email reset link') }}</button>
    </form>

    <p class="text-center small mt-4 mb-0"><a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> {{ __('Back to log in') }}</a></p>
</x-layouts.auth>
