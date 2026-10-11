<x-layouts.member :title="__('Home')">
    <div class="ff-card bg-ff-gradient border-0 mb-4 p-4 p-md-5 position-relative overflow-hidden">
        <x-logo-mark :size="220" class="position-absolute ff-decorative" style="right:-40px;bottom:-60px;opacity:.12;color:#fff" />
        <span class="small fw-semibold text-white-50 text-uppercase">{{ now()->format('l, j F') }}</span>
        <h1 class="text-white mt-1 mb-2">{{ __('Welcome, :name', ['name' => $user->first_name]) }} 👋</h1>
        <p class="text-white mb-4" style="max-width: 560px; opacity:.9">{{ __('Find your church, follow its sermons and live services, give securely with mobile money, and stay connected all week.') }}</p>
        @if (Route::has('churches.index'))
            <a href="{{ route('churches.index') }}" class="btn btn-ff-white px-4"><i class="bi bi-compass me-1"></i> {{ __('Discover churches') }}</a>
        @endif
    </div>

    <div class="row g-4">
        @foreach ([
            ['bi-bookmark-heart', __('Subscribe to churches'), __('Follow as many churches as you like and leave anytime.')],
            ['bi-mic', __('Listen anywhere'), __('Stream sermons or save them for offline listening.')],
            ['bi-phone', __('Give with mobile money'), __('MTN MoMo, Airtel Money, card or bank — with instant receipts.')],
        ] as [$icon, $title, $text])
            <div class="col-md-4">
                <div class="ff-card h-100">
                    <div class="ff-feature-icon"><i class="bi {{ $icon }}"></i></div>
                    <h2 class="h6">{{ $title }}</h2>
                    <p class="small text-ff-muted mb-0">{{ $text }}</p>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.member>
