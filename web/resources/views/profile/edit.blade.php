<x-layouts.member :title="__('Profile & settings')">
    @php
        $tab = request('tab', $errors->updatePassword->isNotEmpty() || $errors->has('password') ? 'security' : ($errors->userDeletion->isNotEmpty() ? 'data' : 'profile'));
        $countries = ['UG' => 'Uganda', 'KE' => 'Kenya', 'TZ' => 'Tanzania', 'RW' => 'Rwanda', 'SS' => 'South Sudan', 'CD' => 'DR Congo', 'BI' => 'Burundi', 'GB' => 'United Kingdom', 'US' => 'United States', 'CA' => 'Canada', 'AE' => 'United Arab Emirates', 'ZA' => 'South Africa', 'DE' => 'Germany', 'SE' => 'Sweden', 'AU' => 'Australia'];
    @endphp

    <x-ff.page-header :title="__('Profile & settings')" :subtitle="__('Manage your account, notifications, security and data.')" />

    <nav class="ff-tabs mb-4" role="tablist">
        @foreach (['profile' => __('Profile'), 'preferences' => __('Preferences'), 'security' => __('Security & devices'), 'data' => __('Data & privacy')] as $key => $label)
            <a href="{{ route('profile.edit', ['tab' => $key]) }}" @class(['ff-tab', 'active' => $tab === $key]) role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}">{{ $label }}</a>
        @endforeach
        @if (Route::has('notification-preferences.edit'))
            <a href="{{ route('notification-preferences.edit') }}" class="ff-tab">{{ __('Notifications per church') }}</a>
        @endif
    </nav>

    @if ($tab === 'profile')
        <div class="row g-4">
            <div class="col-lg-8">
                <x-ff.card :title="__('Personal information')">
                    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" novalidate>
                        @csrf
                        @method('PATCH')
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <x-ff.avatar :user="$user" size="xl" />
                            <div>
                                <label for="avatar" class="form-label mb-1">{{ __('Profile photo') }}</label>
                                <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm @error('avatar') is-invalid @enderror">
                                @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">{{ __('JPG, PNG or WebP · max 6 MB') }}</div>
                            </div>
                        </div>
                        <div class="row g-x-3">
                            <div class="col-md-6"><x-ff.input name="name" :label="__('Full name')" :value="$user->name" required /></div>
                            <div class="col-md-6"><x-ff.input name="email" type="email" :label="__('Email')" :value="$user->email" required /></div>
                        </div>
                        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                            <div class="alert alert-warning small">{{ __('Your email address is unverified.') }}</div>
                        @endif
                        <div class="row">
                            <div class="col-md-6"><x-ff.input name="phone" type="tel" :label="__('Phone')" :value="$user->phone" placeholder="07XX XXX XXX" /></div>
                            <div class="col-md-6"><x-ff.select name="gender" :label="__('Gender')" :options="['male' => __('Male'), 'female' => __('Female')]" :value="$user->gender" :placeholder="__('Prefer not to say')" /></div>
                            <div class="col-md-6"><x-ff.select name="country" :label="__('Country')" :options="$countries" :value="$user->country" required /></div>
                            <div class="col-md-6"><x-ff.input name="city" :label="__('City / town')" :value="$user->city" /></div>
                            <div class="col-md-6"><x-ff.select name="language" :label="__('Language')" :options="config('faithfellows.languages')" :value="$user->language" required /></div>
                            <div class="col-md-6"><x-ff.select name="timezone" :label="__('Timezone')" :options="$timezones" :value="$user->timezone" required /></div>
                            <div class="col-md-6"><x-ff.input name="date_of_birth" type="date" :label="__('Birthday')" :value="$user->date_of_birth?->format('Y-m-d')" :help="__('Your pastors may send you a blessing.')" /></div>
                            <div class="col-md-6"><x-ff.input name="wedding_anniversary" type="date" :label="__('Wedding anniversary')" :value="$user->wedding_anniversary?->format('Y-m-d')" /></div>
                        </div>
                        <x-ff.textarea name="bio" :label="__('About me')" :value="$user->bio" rows="3" maxlength="500" />
                        <button class="btn btn-ff-primary px-4">{{ __('Save profile') }}</button>
                    </form>
                </x-ff.card>
            </div>
            <div class="col-lg-4">
                <div class="ff-card ff-card-mist">
                    <h2 class="h6">{{ __('One account, many churches') }}</h2>
                    <p class="small mb-0">{{ __('Your profile is shared across every church you subscribe to. Churches only see your email if you allow it under Preferences.') }}</p>
                </div>
            </div>
        </div>
    @elseif ($tab === 'preferences')
        <x-ff.card :title="__('Preferences')" class="mw-100" style="max-width: 760px">
            <form method="POST" action="{{ route('profile.preferences') }}" novalidate>
                @csrf
                @method('PATCH')
                <div class="ff-form-section">
                    <h3>{{ __('Quiet hours') }}</h3>
                    <p class="small text-ff-muted">{{ __('We hold notifications during this window and deliver them afterwards.') }}</p>
                    <div class="row">
                        <div class="col-6"><x-ff.input name="quiet_hours_start" type="time" :label="__('From')" :value="$user->quiet_hours_start ? substr($user->quiet_hours_start, 0, 5) : null" /></div>
                        <div class="col-6"><x-ff.input name="quiet_hours_end" type="time" :label="__('Until')" :value="$user->quiet_hours_end ? substr($user->quiet_hours_end, 0, 5) : null" /></div>
                    </div>
                </div>
                <div class="ff-form-section">
                    <h3>{{ __('Delivery') }}</h3>
                    <x-ff.checkbox name="digest_mode" :label="__('Digest mode — one daily summary instead of many pings')" :checked="$user->digest_mode" switch />
                    <x-ff.checkbox name="low_bandwidth" :label="__('Data-saver mode — text first, audio-only streams, fewer images')" :checked="$user->low_bandwidth" switch />
                </div>
                <div class="ff-form-section">
                    <h3>{{ __('Privacy') }}</h3>
                    <x-ff.checkbox name="email_consent" :label="__('Share my email address with churches I subscribe to')" :checked="$user->email_consent" switch />
                    <x-ff.checkbox name="directory_opt_in" :label="__('List me in member directories of my churches')" :checked="$user->directory_opt_in" switch />
                </div>
                <div class="ff-form-section">
                    <h3>{{ __('Two-factor authentication') }}</h3>
                    <x-ff.checkbox name="two_factor_enabled" :label="__('Require an emailed code when I sign in')" :checked="$user->two_factor_enabled" switch :help="__('Recommended for church admins and finance staff.')" />
                </div>
                <button class="btn btn-ff-primary px-4">{{ __('Save preferences') }}</button>
            </form>
        </x-ff.card>
    @elseif ($tab === 'security')
        <div class="row g-4">
            <div class="col-lg-6">
                <x-ff.card :title="__('Change password')">
                    @if (session('status') === 'password-updated')
                        <div class="alert alert-success small">{{ __('Password updated.') }}</div>
                    @endif
                    <form method="POST" action="{{ route('password.update') }}" novalidate>
                        @csrf
                        @method('PUT')
                        @foreach (['current_password' => __('Current password'), 'password' => __('New password'), 'password_confirmation' => __('Confirm new password')] as $field => $label)
                            <div class="mb-3">
                                <label for="up_{{ $field }}" class="form-label">{{ $label }}</label>
                                <input type="password" name="{{ $field }}" id="up_{{ $field }}" class="form-control @if ($errors->updatePassword->has($field)) is-invalid @endif" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}">
                                @if ($errors->updatePassword->has($field))<div class="invalid-feedback">{{ $errors->updatePassword->first($field) }}</div>@endif
                            </div>
                        @endforeach
                        <button class="btn btn-ff-primary px-4">{{ __('Update password') }}</button>
                    </form>
                </x-ff.card>
            </div>
            <div class="col-lg-6">
                <x-ff.card :title="__('Devices & sessions')" :subtitle="__('Where you are signed in.')">
                    <ul class="list-unstyled mb-4">
                        @foreach ($sessions as $session)
                            <li class="d-flex gap-3 align-items-center py-2 border-bottom">
                                <i class="bi {{ $session->is_mobile ? 'bi-phone' : 'bi-laptop' }} fs-4 text-ff-muted"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-ff-black small">{{ $session->agent }}</div>
                                    <div class="small-xs text-ff-muted">{{ $session->ip }} · {{ $session->is_current ? __('This device') : $session->last_active->diffForHumans() }}</div>
                                </div>
                                @if ($session->is_current)<span class="ff-badge ff-badge-success">{{ __('Active') }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('profile.sessions.destroy') }}" class="d-flex gap-2 align-items-start">
                        @csrf
                        @method('DELETE')
                        <div class="flex-grow-1">
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ __('Confirm with your password') }}" aria-label="{{ __('Password') }}">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button class="btn btn-ff-outline text-nowrap">{{ __('Log out others') }}</button>
                    </form>
                </x-ff.card>
            </div>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-6">
                <x-ff.card :title="__('Download your data')">
                    <p class="text-ff-muted small">{{ __('Get a copy of your profile, subscriptions, giving, prayer requests and more as a JSON file.') }}</p>
                    <a href="{{ route('profile.export') }}" class="btn btn-ff-outline"><i class="bi bi-download me-1"></i> {{ __('Export my data') }}</a>
                </x-ff.card>
            </div>
            <div class="col-lg-6">
                <x-ff.card :title="__('Delete account')">
                    <p class="text-ff-muted small">{{ __('Your personal data is removed. Donation records are kept anonymously for church accounting.') }}</p>
                    <form method="POST" action="{{ route('profile.destroy') }}" data-ff-confirm="{{ __('Permanently delete your account?') }}">
                        @csrf
                        @method('DELETE')
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control @if ($errors->userDeletion->has('password')) is-invalid @endif" placeholder="{{ __('Your password') }}" aria-label="{{ __('Password') }}">
                            @if ($errors->userDeletion->has('password'))<div class="invalid-feedback">{{ $errors->userDeletion->first('password') }}</div>@endif
                        </div>
                        <button class="btn btn-danger rounded-pill px-4">{{ __('Delete my account') }}</button>
                    </form>
                </x-ff.card>
            </div>
        </div>
    @endif
</x-layouts.member>
