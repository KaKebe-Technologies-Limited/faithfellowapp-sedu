<x-layouts.auth :title="__('Confirm password')">
    <h1 class="mb-1">{{ __('Confirm your password') }}</h1>
    <p class="text-ff-muted mb-4">{{ __('This is a secure area. Please confirm your password to continue.') }}</p>

    <form method="POST" action="{{ route('password.confirm') }}" novalidate>
        @csrf
        <x-ff.input name="password" type="password" :label="__('Password')" required autofocus autocomplete="current-password" />
        <button class="btn btn-ff-primary w-100 py-2">{{ __('Confirm') }}</button>
    </form>
</x-layouts.auth>
