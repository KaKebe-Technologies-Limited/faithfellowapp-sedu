<x-layouts.super-admin :title="__('Google sign-in')">
    <x-ff.page-header :title="__('Google sign-in')" :subtitle="__('The Google account login behind “Continue with Google”, in the app and on the website.')" />

    @if (session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif

    <div class="row g-4">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('super-admin.google.update') }}" class="ff-card">
                @csrf @method('PUT')
                <div class="d-flex align-items-center gap-2 mb-3">
                    <h2 class="h6 mb-0">{{ __('OAuth client') }}</h2>
                    @if ($v['on'])<span class="ff-badge ff-badge-success"><i class="bi bi-check-circle"></i> {{ __('Google sign-in is on') }}</span>@else<span class="ff-badge">{{ __('Not set up') }}</span>@endif
                </div>
                <x-ff.input name="client_id" :label="__('Client ID')" :value="old('client_id', $v['client_id'])" required :help="__('From Google Cloud → APIs & Services → Credentials → your Web client.')" />
                <div class="mb-3">
                    <label class="form-label" for="google-secret">{{ __('Client secret') }}</label>
                    <input id="google-secret" type="password" name="client_secret" class="form-control @error('client_secret') is-invalid @enderror" autocomplete="new-password" placeholder="{{ $v['has_secret'] ? '••••••••••••••••  '.__('(saved; leave empty to keep it)') : 'GOCSPX-…' }}">
                    <div class="form-text">{{ __('Saved encrypted and never shown again.') }}</div>
                </div>
                <button class="btn btn-ff-primary"><i class="bi bi-check2"></i> {{ __('Save') }}</button>
            </form>
        </div>
        <div class="col-xl-5">
            <div class="ff-card">
                <h2 class="h6 mb-2">{{ __('One thing to do in Google Cloud') }}</h2>
                <p class="small text-ff-muted">{{ __('Open this client in Google Cloud and add this address under “Authorised redirect URIs”, then save there:') }}</p>
                <div class="input-group mb-3">
                    <input class="form-control form-control-sm" value="{{ $v['redirect_uri'] }}" readonly onclick="this.select()" aria-label="{{ __('Redirect URI') }}">
                </div>
                <p class="small text-ff-muted mb-0">{{ __('Also make sure the OAuth consent screen is published (not just “Testing”), otherwise only the test users you list can sign in.') }}</p>
            </div>
        </div>
    </div>
</x-layouts.super-admin>
