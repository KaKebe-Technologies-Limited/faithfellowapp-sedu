<x-layouts.super-admin :title="__('Email')">
    <x-ff.page-header :title="__('Email')" :subtitle="__('The email account FaithFellows sends from: thank-you notes and receipts to givers, and sign-in codes.')" />

    @if (session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif

    <div class="row g-4">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('super-admin.email.update') }}" class="ff-card">
                @csrf @method('PUT')
                <div class="d-flex align-items-center gap-2 mb-3">
                    <h2 class="h6 mb-0">{{ __('Gmail account') }}</h2>
                    @if ($active)<span class="ff-badge ff-badge-success"><i class="bi bi-check-circle"></i> {{ __('Sending from this account') }}</span>
                    @elseif ($v['enabled'] && ! $v['has_password'])<span class="ff-badge ff-badge-warning">{{ __('Add the App Password') }}</span>
                    @else<span class="ff-badge">{{ __('Not in use') }}</span>@endif
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="mail-enabled" name="enabled" value="1" @checked(old('enabled', $v['enabled']))>
                    <label class="form-check-label fw-semibold" for="mail-enabled">{{ __('Send emails from this account') }}</label>
                </div>

                <div class="row g-3">
                    <div class="col-md-8"><x-ff.input name="username" :label="__('Gmail address')" :value="old('username', $v['username'])" required type="email" :help="__('The account that sends. For FaithFellows this is faithfellowsofficial@gmail.com.')" /></div>
                    <div class="col-md-4"><x-ff.input name="from_name" :label="__('Sender name')" :value="old('from_name', $v['from_name'])" required /></div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold" for="mail-password">{{ __('App Password') }}</label>
                        <input id="mail-password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" placeholder="{{ $v['has_password'] ? '••••••••••••••••  '.__('(saved; leave empty to keep it)') : __('16 letters, for example abcd efgh ijkl mnop') }}">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('Made in the Google account: Security → 2-Step Verification → App passwords. It is saved encrypted and never shown again.') }}</div>
                    </div>
                    <div class="col-md-6"><x-ff.input name="from_address" :label="__('Send as')" :value="old('from_address', $v['from_address'])" required type="email" /></div>
                    <div class="col-md-4"><x-ff.input name="host" :label="__('Mail host')" :value="old('host', $v['host'])" required /></div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold" for="mail-port">{{ __('Port') }}</label>
                        <select id="mail-port" name="port" class="form-select">
                            @foreach (['587' => '587', '465' => '465', '2525' => '2525', '25' => '25'] as $p => $l)<option value="{{ $p }}" @selected((string) old('port', $v['port']) === $p)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <button class="btn btn-ff-primary mt-3"><i class="bi bi-check2"></i> {{ __('Save') }}</button>
            </form>
        </div>

        <div class="col-xl-5">
            <form method="POST" action="{{ route('super-admin.email.test') }}" class="ff-card mb-3">
                @csrf
                <h2 class="h6 mb-2">{{ __('Send a test email') }}</h2>
                <p class="small text-ff-muted">{{ __('Save the account first, then send a test to your own address to make sure it works.') }}</p>
                <x-ff.input name="to" type="email" :label="__('Send to')" :value="old('to', auth()->user()->email)" required />
                <button class="btn btn-ff-ghost mt-2"><i class="bi bi-send"></i> {{ __('Send test') }}</button>
            </form>

            <div class="ff-card">
                <h2 class="h6 mb-2">{{ __('How it is set up now') }}</h2>
                <dl class="row small mb-0">
                    <dt class="col-5 text-ff-muted fw-normal">{{ __('Sending method') }}</dt><dd class="col-7">{{ $driver }}@if (in_array($driver, ['log', 'array'], true)) <span class="text-ff-muted">({{ __('emails are only written to a log file') }})</span>@endif</dd>
                    <dt class="col-5 text-ff-muted fw-normal">{{ __('Sending from') }}</dt><dd class="col-7">{{ $sendingFrom }}</dd>
                </dl>
                <p class="small text-ff-muted mt-3 mb-0">{{ __('Emails are sent by the queue, so the “queue:work” job must be running on the server (see the deployment notes).') }}</p>
            </div>
        </div>
    </div>
</x-layouts.super-admin>
