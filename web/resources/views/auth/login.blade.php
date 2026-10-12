<x-layouts.auth :title="__('Log in')">
    <h1 class="mb-1">{{ __('Welcome back') }}</h1>
    <p class="text-ff-muted mb-4">{{ __('Sign in to your churches, sermons and giving.') }}</p>

    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <x-ff.input name="email" type="email" :label="__('Email')" required autofocus autocomplete="username" />
        <div class="d-flex justify-content-between align-items-center">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            @if (Route::has('password.request'))
                <a class="small" href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
            @endif
        </div>
        <x-ff.input name="password" type="password" required autocomplete="current-password" />

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label" for="remember">{{ __('Keep me signed in') }}</label>
        </div>

        <button class="btn btn-ff-primary w-100 py-2">{{ __('Log in') }}</button>
    </form>

    <div class="ff-divider my-4">{{ __('or') }}</div>
    <a href="{{ route('magic-link.create') }}" class="btn btn-ff-outline w-100"><i class="bi bi-envelope-paper me-1"></i> {{ __('Email me a sign-in link') }}</a>

    <p class="text-center small mt-4 mb-0">
        {{ __('New to FaithFellows?') }} <a href="{{ route('register') }}" class="fw-semibold">{{ __('Create an account') }}</a>
    </p>
</x-layouts.auth>
