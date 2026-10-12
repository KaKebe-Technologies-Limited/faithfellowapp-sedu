<x-layouts.member :title="$bookName.' '.$chapter">
    <div class="ff-bible-page" x-data="bibleReader({ translation: @js($translations[$translation]['short'] ?? strtoupper($translation)), highlightUrl: @js(route('bible.highlight')) })">
        <div class="ff-bible-toolbar">
            <a href="{{ route('bible.index') }}" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('All books') }}"><i class="bi bi-grid-3x3-gap"></i></a>
            <div class="dropdown">
                <button class="btn btn-sm btn-ff-soft dropdown-toggle fw-bold" data-bs-toggle="dropdown" aria-label="{{ __('Choose chapter') }}">{{ $bookName }} {{ $chapter }}</button>
                <div class="dropdown-menu p-3" style="width: min(360px, 90vw)">
                    <div class="small fw-semibold text-ff-muted mb-2">{{ $bookName }}</div>
                    <div class="ff-chapter-grid">
                        @for ($c = 1; $c <= $chapters; $c++)
                            <a href="{{ route('bible.read', [$book, $c, 't' => request('t')]) }}" @class(['active' => $c === $chapter])>{{ $c }}</a>
                        @endfor
                    </div>
                </div>
            </div>
            <form method="GET" class="m-0">
                <select name="t" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Translation') }}">
                    @foreach ($translations as $key => $t)<option value="{{ $key }}" @selected($translation === $key)>{{ $t['short'] }}</option>@endforeach
                </select>
            </form>
            <div class="ms-auto d-flex align-items-center gap-1">
                <button type="button" class="btn btn-sm btn-ff-ghost" @click="toggleLines()" :aria-pressed="lines" :title="lines ? '{{ __('Show as paragraphs') }}' : '{{ __('One verse per line') }}'" aria-label="{{ __('Change layout') }}"><i class="bi" :class="lines ? 'bi-text-paragraph' : 'bi-list-ol'"></i></button>
                <button type="button" class="btn btn-sm btn-ff-ghost" @click="resize(-1)" aria-label="{{ __('Smaller text') }}">A<sup>−</sup></button>
                <button type="button" class="btn btn-sm btn-ff-ghost fw-bold" @click="resize(1)" aria-label="{{ __('Larger text') }}">A<sup>+</sup></button>
            </div>
        </div>

        <header class="ff-scripture-heading">
            <span class="ff-eyebrow">{{ $translations[$translation]['name'] ?? '' }}</span>
            <h1 class="h2">{{ $bookName }} {{ $chapter }}</h1>
        </header>

        @if ($error)
            <x-ff.empty-state icon="bi-wifi-off" :title="__('Chapter unavailable')" :message="$error">
                <a href="{{ request()->fullUrl() }}" class="btn btn-ff-primary mt-2">{{ __('Try again') }}</a>
            </x-ff.empty-state>
        @else
            <article class="ff-scripture is-lines" :class="{ 'is-lines': lines }" :style="`font-size: ${size}px`" style="font-size: 19px" @click="pick($event)" @keydown.enter="pick($event)"
                data-label="{{ $bookName }} {{ $chapter }}">
                <span class="ff-chapter-drop" aria-hidden="true">{{ $chapter }}</span>
                @foreach ($verses as $verse)
                    @php($key = $book.'.'.$chapter.'.'.$verse['v'])
                    <span @class(['ff-verse', 'is-target' => $target && $verse['v'] >= $target[0] && $verse['v'] <= ($target[1] ?? $target[0])])
                        id="v{{ $verse['v'] }}" data-key="{{ $key }}" @isset($highlights[$key]) data-hl="{{ $highlights[$key] }}" @endisset tabindex="0" role="button"><sup>{{ $verse['v'] }}</sup><span class="ff-verse-text">{{ $verse['t'] }}</span> </span>
                @endforeach
            </article>
            <p class="small text-ff-muted mt-3 mb-0 ff-verse-hint"><i class="bi bi-hand-index"></i> {{ __('Tap a verse to copy, highlight, share or add it to your notes.') }}</p>
        @endif

        <nav class="ff-chapter-nav" aria-label="{{ __('Chapters') }}">
            @if ($previous)
                <a href="{{ route('bible.read', [$previous[0], $previous[1], 't' => request('t')]) }}"><small><i class="bi bi-arrow-left"></i> {{ __('Previous') }}</small>{{ \App\Support\Bible\Books::name($previous[0]) }} {{ $previous[1] }}</a>
            @else<span class="flex-grow-1"></span>@endif
            @if ($next)
                <a href="{{ route('bible.read', [$next[0], $next[1], 't' => request('t')]) }}" class="text-end"><small>{{ __('Next') }} <i class="bi bi-arrow-right"></i></small>{{ \App\Support\Bible\Books::name($next[0]) }} {{ $next[1] }}</a>
            @endif
        </nav>

        @include('member.bible._verse-bar')
    </div>

    @include('partials.note-taker', ['context' => 'bible:'.$book.'.'.$chapter, 'reference' => $bookName.' '.$chapter])
</x-layouts.member>
