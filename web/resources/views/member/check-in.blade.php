<x-layouts.member :title="__('Check in')">
    <div class="ff-card ff-checkin-hero my-4">
        <x-ff.church-avatar :church="$church" size="lg" class="mb-3" />
        <h1 class="h3">{{ $church->name }}</h1>
        <p class="text-ff-muted">{{ today()->format('l, j F Y') }}</p>

        @if ($already)
            <div class="ff-checkin-icon is-done"><i class="bi bi-check2-circle"></i></div>
            <p class="fw-semibold text-ff-black">{{ __('You are checked in. Welcome — we are glad you are here!') }}</p>
        @elseif ($signedUrl)
            <div class="ff-checkin-icon"><i class="bi bi-qr-code-scan"></i></div>
            <form method="POST" action="{{ $signedUrl }}">@csrf
                <button class="btn btn-ff-primary btn-lg w-100">{{ __('Check me in') }}</button>
            </form>
        @else
            <div x-data="geoCheckin({ url: @js(route('check-in.store', $church)) })">
                <div class="ff-checkin-icon" :class="state === 'done' && 'is-done'">
                    <i class="bi" :class="state === 'done' ? 'bi-check2-circle' : 'bi-geo-alt'"></i>
                </div>
                <button class="btn btn-ff-primary btn-lg w-100" @click="locate()" :disabled="state === 'locating' || state === 'done'" x-show="state !== 'done'">
                    <span x-show="state !== 'locating'">{{ __('Check in with my location') }}</span>
                    <span x-show="state === 'locating'" x-cloak><span class="spinner-border spinner-border-sm"></span> {{ __('Finding you…') }}</span>
                </button>
                <p class="small mt-3 mb-0" :class="state === 'error' ? 'text-danger' : 'text-ff-black'" x-text="message" aria-live="polite"></p>
                <p class="small text-ff-muted mt-2">{{ __('Or scan the QR code displayed at church.') }}</p>
            </div>
        @endif

        <a href="{{ route('dashboard') }}" class="small d-inline-block mt-3">{{ __('Back to my feed') }}</a>
    </div>
</x-layouts.member>
