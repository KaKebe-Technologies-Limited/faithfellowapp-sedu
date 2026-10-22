<x-layouts.church-admin :church="$church" :title="__('Invite link')">
    <x-ff.page-header :title="__('Invite link')" :subtitle="__('One link for your church. Share it on WhatsApp, on social media or in the bulletin: whoever opens it is taken to the app, and joins your church as soon as they have an account.')">
        @if ($url)
            <a href="{{ route('church-admin.invite.poster', $church) }}" class="btn btn-ff-primary" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Print QR poster') }}</a>
        @endif
    </x-ff.page-header>

    @unless ($ready)
        <div class="alert alert-info"><i class="bi bi-info-circle"></i> {{ __('Invite links are being switched on for the platform. Please check again soon.') }}</div>
    @else
        <div class="row g-4">
            <div class="col-lg-7 d-grid gap-4 align-content-start">
                <x-ff.card :title="__('Your link')" :subtitle="__('Anyone can use it. Phones with the app open it right away; others are sent to download the app first.')">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" value="{{ $url }}" readonly aria-label="{{ __('Invite link') }}" onfocus="this.select()">
                        <button type="button" class="btn btn-ff-primary" data-ff-copy="{{ $url }}"><i class="bi bi-link-45deg"></i> {{ __('Copy link') }}</button>
                    </div>
                    <x-ff.share :url="$url" :title="$message" />
                    <p class="small text-ff-muted mt-3 mb-0">{{ __('Tip: the church name and picture show in the preview when you paste the link into WhatsApp or Facebook.') }}</p>
                </x-ff.card>

                <x-ff.card :title="__('Church code')" :subtitle="__('Members can also type this code in the app (Create account → Have a church code?).')">
                    <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                        <span class="fs-2 fw-bold text-ff-primary" style="letter-spacing:.14em">{{ $code }}</span>
                        <button type="button" class="btn btn-sm btn-ff-soft" data-ff-copy="{{ $code }}"><i class="bi bi-clipboard"></i> {{ __('Copy code') }}</button>
                    </div>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('church-admin.invite.update', $church) }}" class="row g-2 align-items-start" data-ff-confirm="{{ __('Change the code? The link you shared before will stop working.') }}">@csrf @method('PUT')
                            <div class="col-sm-7">
                                <input type="text" name="join_code" value="{{ old('join_code', $code) }}" maxlength="24" class="form-control @error('join_code') is-invalid @enderror" aria-label="{{ __('Church code') }}" autocomplete="off" style="text-transform:uppercase">
                                @error('join_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">{{ __('Letters and numbers, 4 to 20. A code with your church name is easy to remember, for example GRACELIRA.') }}</div>
                            </div>
                            <div class="col-sm-5 d-flex gap-2">
                                <button class="btn btn-ff-outline">{{ __('Change code') }}</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('church-admin.invite.regenerate', $church) }}" data-ff-confirm="{{ __('Make a new link? The link you shared before will stop working.') }}" class="mt-2">@csrf
                            <button class="btn btn-sm btn-ff-ghost text-danger"><i class="bi bi-arrow-repeat"></i> {{ __('Make a new random code') }}</button>
                        </form>
                    @endif
                </x-ff.card>

                <x-ff.card :title="__('How it works')">
                    <ol class="small mb-0 ps-3 d-grid gap-2">
                        <li>{{ __('Someone taps your link on their phone and sees :church.', ['church' => $church->name]) }}</li>
                        <li>{{ __('If they have the FaithFellows app it opens on your church. If not, they download it first.') }}</li>
                        <li>{{ __('They create an account (or sign in) and follow :church automatically. If you approve new members, you will find them waiting under Members.', ['church' => $church->name]) }}</li>
                    </ol>
                </x-ff.card>
            </div>

            <div class="col-lg-5 d-grid gap-4 align-content-start">
                <x-ff.card :title="__('QR code')" :subtitle="__('Put it on the noticeboard, the bulletin or the screen at church. Phones open it with the camera.')" class="text-center">
                    <div class="d-inline-block bg-white rounded-4 p-2" style="max-width: 260px" role="img" aria-label="{{ __('QR code of the invite link') }}">{!! $qr !!}</div>
                    <div class="mt-3"><a href="{{ route('church-admin.invite.poster', $church) }}" class="btn btn-ff-soft" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Print poster') }}</a></div>
                </x-ff.card>

                <div class="row g-3">
                    <div class="col-6"><x-ff.stat-card :label="__('Link opened')" :value="number_format($stats['opened'])" icon="bi-cursor">{{ __(':n in the last 30 days', ['n' => number_format($stats['opened_30'])]) }}</x-ff.stat-card></div>
                    <div class="col-6"><x-ff.stat-card :label="__('Joined by link')" :value="number_format($stats['joined'])" icon="bi-person-plus" primary>{{ __(':n in the last 30 days', ['n' => number_format($stats['joined_30'])]) }}</x-ff.stat-card></div>
                </div>
            </div>
        </div>
    @endunless
</x-layouts.church-admin>
