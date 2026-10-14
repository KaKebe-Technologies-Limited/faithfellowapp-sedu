@php($layout = auth()->check() ? 'layouts.member' : 'layouts.public')
<x-dynamic-component :component="$layout" :title="$devotional->title" :description="$devotional->scripture_text">
    <article @class(['container py-5' => ! auth()->check()]) style="max-width: 820px">
        <div class="small text-ff-muted mb-2">
            <a href="{{ route('churches.show', $devotional->church) }}">{{ $devotional->church->name }}</a> · {{ $devotional->publish_date->format('l, j F Y') }}
        </div>
        <h1 class="h2 mb-4">{{ $devotional->title }}</h1>

        @if ($devotional->scripture_text || $devotional->scripture)
            <div class="ff-verse-card mb-4">
                @if ($devotional->scripture_text)<blockquote>“{{ $devotional->scripture_text }}”</blockquote>@endif
                <cite>@auth<a href="{{ route('bible.passage', ['ref' => $devotional->scripture]) }}" class="text-reset">{{ $devotional->scripture }} <i class="bi bi-book small"></i></a>@else{{ $devotional->scripture }}@endauth</cite>
                @if ($devotional->scripture_text)
                    <div class="mt-3"><button type="button" class="btn btn-sm btn-ff-white" data-ff-verse-download data-verse="{{ $devotional->scripture_text }}" data-reference="{{ $devotional->scripture }}" data-church="{{ $devotional->church->name }}"><i class="bi bi-image"></i> {{ __('Download verse card') }}</button></div>
                @endif
            </div>
        @endif

        @if ($devotional->audio_src)
            <div class="ff-card ff-card-sm mb-4 d-flex align-items-center gap-3">
                <i class="bi bi-headphones fs-3 text-ff-primary"></i>
                <div class="flex-grow-1">
                    <div class="small fw-semibold text-ff-black mb-1">{{ __('Listen (data-light audio)') }}</div>
                    <audio controls preload="none" class="w-100" src="{{ $devotional->audio_src }}"></audio>
                </div>
            </div>
        @endif

        <div class="ff-prose mb-4">{!! ff_safe_html($devotional->body) !!}</div>

        @if ($devotional->prayer)
            <div class="ff-card ff-card-mist mb-4">
                <h2 class="h6"><i class="bi bi-chat-heart"></i> {{ __('Prayer') }}</h2>
                <p class="mb-0 fst-italic">{{ $devotional->prayer }}</p>
            </div>
        @endif

        <x-ff.share :url="route('devotionals.show', $devotional)" :title="$devotional->title" />
    </article>
    @include('partials.note-taker', ['context' => 'devotional:'.$devotional->id, 'reference' => $devotional->title, 'churchId' => $devotional->church_id])
</x-dynamic-component>
