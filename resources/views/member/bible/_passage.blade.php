{{-- Passage text. Expects $passage (BibleService::passage) and $translation. Optional $compact. --}}
@if (! $passage)
    <p class="small text-ff-muted mb-0">{{ __('We could not recognise this Bible reference.') }}</p>
@else
    <div class="ff-scripture" style="font-size: {{ ($compact ?? false) ? 17 : 19 }}px">
        @foreach ($passage['sections'] as $section)
            <section class="ff-passage-section">
                <h3 class="d-flex align-items-center gap-2">
                    {{ $section['label'] }}
                    <a href="{{ route('bible.read', [$section['book'], $section['chapter'], 't' => $translation]) }}" class="ms-auto small text-ff-muted text-decoration-none fw-normal" style="letter-spacing:0; text-transform:none">{{ __('Open chapter') }} <i class="bi bi-box-arrow-up-right"></i></a>
                </h3>
                @if ($section['error'])
                    <p class="small text-danger mb-0">{{ $section['error'] }}</p>
                @else
                    @foreach ($section['verses'] as $verse)
                        <span class="ff-verse"><sup>{{ $verse['v'] }}</sup><span class="ff-verse-text">{{ $verse['t'] }}</span> </span>
                    @endforeach
                @endif
            </section>
        @endforeach
    </div>
@endif
