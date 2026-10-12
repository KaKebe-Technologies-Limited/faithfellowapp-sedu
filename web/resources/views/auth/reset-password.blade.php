<x-layouts.auth :title="__('Choose a new password')">
    <h1 class="mb-4">{{ __('Choose a new password') }}</h1>

    <form method="POST" action="{{ route('password.store') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-ff.input name="email" type="email" :label="__('Email')" :value="$request->email" required autocomplete="username" />
        <x-ff.input name="password" type="password" :label="__('New password')" required autofocus autocomplete="new-password" />
        <x-ff.input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />
        <button class="btn btn-ff-primary w-100 py-2">{{ __('Reset password') }}</button>
    </form>
</x-layouts.auth>
