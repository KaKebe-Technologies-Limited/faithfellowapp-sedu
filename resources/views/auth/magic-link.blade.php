<x-layouts.auth :title="__('Sign in with email link')">
    <h1 class="mb-1">{{ __('Sign in without a password') }}</h1>
    <p class="text-ff-muted mb-4">{{ __('We will email you a one-time link that signs you in instantly.') }}</p>

    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('magic-link.store') }}" novalidate>
        @csrf
        <x-ff.input name="email" type="email" :label="__('Email')" required autofocus />
        <button class="btn btn-ff-primary w-100 py-2">{{ __('Send sign-in link') }}</button>
    </form>
    <p class="text-center small mt-4 mb-0"><a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> {{ __('Use password instead') }}</a></p>
</x-layouts.auth>
