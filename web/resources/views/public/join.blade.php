<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @if ($church)
        <x-ff.head :title="__('Join :church', ['church' => $church->name])" :description="__('Follow :church on FaithFellows: sermons, events, prayer and giving in one app.', ['church' => $church->name])" :image="$church->cover_url ?: $church->logo_url" :noindex="true" />
    @else
        <x-ff.head :title="__('Church link not found')" :noindex="true" />
    @endif
    <style>
        .ff-join { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; background: linear-gradient(160deg, #1E0710 0%, #4A0F29 55%, #7D1A39 100%); }
        .ff-join-card { width: 100%; max-width: 440px; background: #fff; border-radius: 28px; padding: 28px 24px 22px; box-shadow: 0 24px 60px rgba(0, 0, 0, .35); text-align: center; }
        .ff-join-logo { width: 88px; height: 88px; border-radius: 24px; object-fit: cover; margin: -68px auto 14px; border: 4px solid #fff; background: #fff; box-shadow: 0 8px 24px rgba(0, 0, 0, .2); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 30px; color: #fff; }
        .ff-join-card h1 { font-size: 1.45rem; line-height: 1.25; margin: 0 0 4px; color: #1A1316; }
        .ff-join-kicker { font-size: .78rem; letter-spacing: .12em; text-transform: uppercase; color: #BC2649; font-weight: 700; margin-bottom: 6px; }
        .ff-join-meta { color: #6B5E65; font-size: .92rem; margin-bottom: 18px; }
        .ff-join-btn { display: block; width: 100%; border-radius: 999px; padding: 14px 18px; font-weight: 700; font-size: 1.02rem; text-decoration: none; margin-top: 10px; border: 2px solid #BC2649; }
        .ff-join-btn.primary { background: #BC2649; color: #fff; }
        .ff-join-btn.outline { background: #fff; color: #BC2649; }
        .ff-join-btn.quiet { border-color: transparent; color: #6B5E65; font-weight: 600; font-size: .92rem; padding: 8px; }
        .ff-join-code { margin-top: 20px; background: #FBF1F4; border-radius: 18px; padding: 14px 16px; }
        .ff-join-code b { display: block; font-size: 1.5rem; letter-spacing: .14em; color: #7D1A39; margin: 2px 0 6px; }
        .ff-join-code small { color: #6B5E65; display: block; line-height: 1.4; }
        .ff-join-copy { background: none; border: 0; color: #BC2649; font-weight: 700; padding: 4px 8px; }
        .ff-join-note { color: #8B7A83; font-size: .82rem; margin-top: 14px; line-height: 1.45; }
        .ff-join-qr svg { max-width: 190px; height: auto; }
        .ff-join-cover { height: 96px; margin: -28px -24px 0; border-radius: 28px 28px 0 0; background-size: cover; background-position: center; }
    </style>
</head>
<body style="margin:0">
<main class="ff-join">
    @if (! $church)
        <section class="ff-join-card">
            <div class="ff-join-kicker">FaithFellows</div>
            <h1>{{ __('This church link is not working') }}</h1>
            <p class="ff-join-meta">{{ __('The code :code was not found. It may have been typed wrongly or replaced. Ask your church for its latest link, or find your church in the directory.', ['code' => $typed ?: '—']) }}</p>
            <a class="ff-join-btn primary" href="{{ route('churches.index') }}">{{ __('Find my church') }}</a>
        </section>
    @else
        <section class="ff-join-card" style="margin-top: 56px">
            <div class="ff-join-cover" style="background-image: {{ $church->cover_url ? "url('".e($church->cover_url)."'), " : '' }}linear-gradient(135deg, {{ e($church->brand_color ?: '#BC2649') }} 0%, #F27EA8 100%)"></div>
            @if ($church->logo_url)
                <img class="ff-join-logo" src="{{ $church->logo_url }}" alt="{{ $church->name }}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
            @endif
            <div class="ff-join-logo" style="background: {{ $church->brand_color ?: '#BC2649' }}; @if ($church->logo_url) display:none @endif">{{ ff_initials($church->name) }}</div>

            <div class="ff-join-kicker">{{ __('You are invited') }}</div>
            <h1>{{ $church->name }}</h1>
            <div class="ff-join-meta">
                {{ collect([$church->city, $church->denomination])->filter()->implode(' · ') }}
                @if ($followers > 0)
                    {{ collect([$church->city, $church->denomination])->filter()->isNotEmpty() ? ' · ' : '' }}{{ trans_choice(':count follower|:count followers', $followers, ['count' => ff_short_number($followers)]) }}
                @endif
                @if ($church->tagline)<div class="mt-1">{{ $church->tagline }}</div>@endif
            </div>

            @if ($platform === 'android')
                <a class="ff-join-btn primary" href="{{ $intentUrl }}">{{ __('Open in the FaithFellows app') }}</a>
                @if ($androidUrl)<a class="ff-join-btn outline" href="{{ $androidUrl }}">{{ __('Download the app') }}</a>@endif
            @elseif ($platform === 'ios')
                <a class="ff-join-btn primary" href="{{ $openUrl }}">{{ __('Open in the FaithFellows app') }}</a>
                @if ($iosUrl)
                    <a class="ff-join-btn outline" href="{{ $iosUrl }}">{{ __('Get the app for iPhone') }}</a>
                @else
                    <p class="ff-join-note">{{ __('The iPhone app is coming soon. You can join on the website for now.') }}</p>
                @endif
            @else
                <div class="ff-join-qr mb-2" role="img" aria-label="{{ __('QR code of this link') }}">{!! $qr !!}</div>
                <p class="ff-join-meta mb-2">{{ __('Scan this with your phone camera to open it on your phone.') }}</p>
                @if ($androidUrl)<a class="ff-join-btn outline" href="{{ $androidUrl }}">{{ __('Download for Android') }}</a>@endif
                @if ($iosUrl)<a class="ff-join-btn outline" href="{{ $iosUrl }}">{{ __('Get it for iPhone') }}</a>@endif
            @endif
            <a class="ff-join-btn quiet" href="{{ $webUrl }}">{{ __('Continue on the website instead') }}</a>

            <div class="ff-join-code">
                <small>{{ __('Church code') }}</small>
                <b id="ff-join-code">{{ $code }}</b>
                <button type="button" class="ff-join-copy" data-copy="{{ $code }}">{{ __('Copy code') }}</button>
                <small>{{ __('Once the app is on your phone, create your account and you will join :church automatically. If it asks for a church code, type this one.', ['church' => $church->name]) }}</small>
            </div>
            @if ($platform !== 'other')
                <p class="ff-join-note">{{ __('Nothing happened? Open this page in Chrome or Safari: some apps, such as WhatsApp, cannot open other apps from a link.') }}</p>
            @endif
        </section>
    @endif
</main>
<script>
    document.querySelectorAll('[data-copy]').forEach(function (b) {
        b.addEventListener('click', function () {
            var done = function () { b.textContent = @json(__('Copied')); };
            if (navigator.clipboard) { navigator.clipboard.writeText(b.dataset.copy).then(done, done); } else { done(); }
        });
    });
</script>
</body>
</html>
