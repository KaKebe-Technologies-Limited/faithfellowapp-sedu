<x-layouts.member :title="__('Devotionals')">
    <x-ff.page-header :title="__('Devotionals')" :subtitle="__('Daily encouragement from your churches.')">
        @if (Route::has('reading-plans.index'))<a href="{{ route('reading-plans.index') }}" class="btn btn-ff-outline"><i class="bi bi-journal-check"></i> {{ __('Reading plans') }}</a>@endif
    </x-ff.page-header>

    @forelse ($today as $devotional)
        <div class="ff-verse-card mb-4">
            <div class="small fw-semibold text-uppercase mb-2" style="opacity:.8">{{ __('Today') }} · {{ $devotional->church->name }}</div>
            @if ($devotional->scripture_text)<blockquote>“{{ $devotional->scripture_text }}”</blockquote>@endif
            <cite>{{ $devotional->scripture }}</cite>
            <div class="mt-4 d-flex flex-wrap gap-2">
                <a href="{{ route('devotionals.show', $devotional) }}" class="btn btn-ff-white">{{ __('Read: :title', ['title' => $devotional->title]) }}</a>
                @if ($devotional->scripture_text)
                    <button type="button" class="btn text-white border-white rounded-pill" data-ff-verse-download data-verse="{{ $devotional->scripture_text }}" data-reference="{{ $devotional->scripture }}" data-church="{{ $devotional->church->name }}"><i class="bi bi-image"></i> {{ __('Share card') }}</button>
                @endif
            </div>
        </div>
    @empty
        <div class="ff-card ff-card-mist mb-4"><p class="mb-0">{{ __('No devotional has been published for today yet.') }}</p></div>
    @endforelse

    <h2 class="h5 mb-3">{{ __('Earlier devotionals') }}</h2>
    <div class="d-grid gap-3">
        @forelse ($devotionals as $devotional)
            <a href="{{ route('devotionals.show', $devotional) }}" class="ff-card ff-card-sm ff-card-link d-flex align-items-center gap-3">
                <div class="ff-date-tile"><div class="m">{{ $devotional->publish_date->format('M') }}</div><div class="d">{{ $devotional->publish_date->format('j') }}</div></div>
                <div class="min-w-0 flex-grow-1">
                    <div class="fw-semibold text-ff-black">{{ $devotional->title }}</div>
                    <div class="small text-ff-muted">{{ $devotional->scripture }} · {{ $devotional->church->name }}</div>
                </div>
                <i class="bi bi-chevron-right text-ff-muted"></i>
            </a>
        @empty
            <x-ff.empty-state icon="bi-book" :title="__('No devotionals yet')" />
        @endforelse
    </div>
    <div class="mt-4">{{ $devotionals->links() }}</div>
</x-layouts.member>
