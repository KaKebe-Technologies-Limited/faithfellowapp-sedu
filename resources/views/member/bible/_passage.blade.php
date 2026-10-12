{{-- Passage text. Expects $passage (BibleService::passage) and $translation. Optional $compact, $highlights, $translations. --}}
@if (! $passage)
    <p class="small text-ff-muted mb-0">{{ __('We could not recognise this Bible reference.') }}</p>
@else
    @php($highlights = $highlights ?? [])
    <div x-data="bibleReader({ translation: @js($translations[$translation]['short'] ?? strtoupper($translation)), highlightUrl: @js(route('bible.highlight')) })">
        <div class="ff-scripture is-lines" :class="{ 'is-lines': lines }" style="font-size: {{ ($compact ?? false) ? 17 : 19 }}px" @click="pick($event)" @keydown.enter="pick($event)">
            @foreach ($passage['sections'] as $section)
                <section class="ff-passage-section" data-label="{{ \App\Support\Bible\Books::name($section['book']) }} {{ $section['chapter'] }}">
                    <h3 class="d-flex align-items-center gap-2">
                        {{ $section['label'] }}
                        <a href="{{ route('bible.read', [$section['book'], $section['chapter'], 't' => $translation]) }}" class="ms-auto small text-ff-muted text-decoration-none fw-normal" style="letter-spacing:0; text-transform:none">{{ __('Open chapter') }} <i class="bi bi-box-arrow-up-right"></i></a>
                    </h3>
                    @if ($section['error'])
                        <p class="small text-danger mb-0">{{ $section['error'] }}</p>
                    @else
                        @foreach ($section['verses'] as $verse)
                            @php($key = $section['book'].'.'.$section['chapter'].'.'.$verse['v'])
                            <span class="ff-verse" data-key="{{ $key }}" @isset($highlights[$key]) data-hl="{{ $highlights[$key] }}" @endisset tabindex="0" role="button"><sup>{{ $verse['v'] }}</sup><span class="ff-verse-text">{{ $verse['t'] }}</span> </span>
                        @endforeach
                    @endif
                </section>
            @endforeach
        </div>
        <p class="small text-ff-muted mt-3 mb-0 ff-verse-hint"><i class="bi bi-hand-index"></i> {{ __('Tap a verse to copy, highlight, share or add it to your notes.') }}</p>
        @include('member.bible._verse-bar')
    </div>
@endif
