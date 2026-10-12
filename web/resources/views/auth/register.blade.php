<x-layouts.auth :title="__('Create account')">
    <h1 class="mb-1">{{ __('Create your account') }}</h1>
    <p class="text-ff-muted mb-4">{{ __('One email. One account. Many churches.') }}</p>

    <form method="POST" action="{{ route('register') }}" novalidate>
        @csrf
        <x-ff.input name="name" :label="__('Full name')" required autofocus autocomplete="name" />
        <x-ff.input name="email" type="email" :label="__('Email')" required autocomplete="username" />
        <div class="row g-2">
            <div class="col-sm-7">
                <x-ff.input name="phone" type="tel" :label="__('Phone (for mobile money & SMS)')" placeholder="07XX XXX XXX" autocomplete="tel" />
            </div>
            <div class="col-sm-5">
                <x-ff.select name="language" :label="__('Language')" :options="config('faithfellows.languages')" :value="app()->getLocale()" />
            </div>
        </div>
        <x-ff.input name="password" type="password" :label="__('Password')" required autocomplete="new-password" :help="__('At least 8 characters.')" />
        <x-ff.input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />
        <input type="hidden" name="country" value="UG">

        <div class="form-check mb-2">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" value="1" @checked(old('terms'))>
            <label class="form-check-label small" for="terms">
                {!! __('I agree to the :terms and :privacy.', [
                    'terms' => '<a href="'.route('terms').'" target="_blank">'.e(__('Terms of Service')).'</a>',
                    'privacy' => '<a href="'.route('privacy').'" target="_blank">'.e(__('Privacy Policy')).'</a>',
                ]) !!}
            </label>
            @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="email_consent" id="email_consent" value="1" @checked(old('email_consent'))>
            <label class="form-check-label small" for="email_consent">{{ __('Share my email with churches I subscribe to (you can change this anytime).') }}</label>
        </div>

        <button class="btn btn-ff-primary w-100 py-2">{{ __('Create account') }}</button>
    </form>

    <p class="text-center small mt-4 mb-0">
        {{ __('Already have an account?') }} <a href="{{ route('login') }}" class="fw-semibold">{{ __('Log in') }}</a>
    </p>
</x-layouts.auth>
