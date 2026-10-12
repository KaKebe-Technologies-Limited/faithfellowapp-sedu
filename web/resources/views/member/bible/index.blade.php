<x-layouts.member :title="__('Bible')">
    <div class="ff-bible-hero mb-4">
        <div class="row g-4 align-items-center position-relative">
            <div class="col-lg-7">
                <span class="small text-uppercase fw-semibold" style="opacity:.75; letter-spacing:.12em">{{ __('Holy Bible') }}</span>
                <p class="ff-scripture mt-2 mb-1">“{{ __('Thy word is a lamp unto my feet, and a light unto my path.') }}”</p>
                <span class="small" style="opacity:.8">{{ __('Psalm 119:105') }}</span>
            </div>
            <div class="col-lg-5">
                <form method="GET" action="{{ route('bible.index') }}" class="bg-white rounded-4 p-2 d-flex gap-2 shadow-sm">
                    <input name="q" class="form-control border-0" required placeholder="{{ __('Go to… e.g. John 3:16, Psalm 23') }}" aria-label="{{ __('Go to a passage') }}" value="{{ request('q') }}">
                    <button class="btn btn-ff-primary px-3" aria-label="{{ __('Go') }}"><i class="bi bi-arrow-right"></i></button>
                </form>
                @error('q')<div class="small mt-2 text-white">{{ $message }}</div>@enderror
                <div class="d-flex flex-wrap gap-2 mt-3">
                    @if ($last)
                        <a href="{{ route('bible.read', [$last['book'], $last['chapter']]) }}" class="btn btn-sm btn-ff-white"><i class="bi bi-bookmark-fill"></i> {{ __('Continue: :ref', ['ref' => \App\Support\Bible\Books::name($last['book']).' '.$last['chapter']]) }}</a>
                    @endif
                    <a href="{{ route('bible.read', ['JHN', 1]) }}" class="btn btn-sm btn-ff-white" style="opacity:.9">{{ __('Gospel of John') }}</a>
                    <a href="{{ route('bible.read', ['PSA', 23]) }}" class="btn btn-sm btn-ff-white" style="opacity:.9">{{ __('Psalm 23') }}</a>
                </div>
            </div>
        </div>
    </div>

    @if ($recentHighlights->isNotEmpty())
        <section class="mb-4" aria-labelledby="my-highlights">
            <h2 class="h5 mb-3" id="my-highlights"><i class="bi bi-highlighter text-ff-primary"></i> {{ __('My highlights') }}</h2>
            <div class="row g-3">
                @foreach ($recentHighlights as $h)
                    @php([, $swatch] = \App\Models\BibleHighlight::COLORS[$h['color']] ?? \App\Models\BibleHighlight::COLORS['yellow'])
                    <div class="col-md-6 col-xl-4">
                        <a href="{{ $h['url'] }}" class="ff-hl-card" style="--sw: {{ $swatch }}; --sw-soft: {{ $swatch }}88">
                            <div class="small fw-bold text-ff-primary mb-1">{{ $h['label'] }}</div>
                            @if ($h['text'])
                                <div class="ff-scripture"><mark>{{ $h['text'] }}</mark></div>
                            @else
                                <div class="small text-ff-muted">{{ __('Open to read this verse') }} <i class="bi bi-arrow-right"></i></div>
                            @endif
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <h1 class="h4 mb-0 me-auto">{{ __('Books of the Bible') }}</h1>
        <form method="POST" action="{{ route('bible.translation') }}" class="d-flex align-items-center gap-2">@csrf
            <label for="translation" class="small text-ff-muted text-nowrap">{{ __('Translation') }}</label>
            <select name="translation" id="translation" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ($translations as $key => $t)<option value="{{ $key }}" @selected($translation === $key)>{{ $t['short'] }} — {{ $t['name'] }}</option>@endforeach
            </select>
        </form>
        <a href="{{ route('notes.index') }}" class="btn btn-sm btn-ff-soft"><i class="bi bi-journal-text"></i> {{ __('My notes') }}</a>
    </div>

    <div class="row g-4">
        @foreach (['OT' => __('Old Testament'), 'NT' => __('New Testament')] as $testament => $label)
            <div class="col-lg-6">
                <x-ff.card :title="$label" :subtitle="trans_choice(':count book|:count books', $books[$testament]->count())">
                    <div class="ff-book-list">
                        @foreach ($books[$testament] as $b)
                            <details>
                                <summary>{{ __($b['name']) }} <small>{{ $b['chapters'] }}</small></summary>
                                <div class="ff-chapter-grid">
                                    @for ($c = 1; $c <= $b['chapters']; $c++)
                                        <a href="{{ route('bible.read', [$b['code'], $c]) }}">{{ $c }}</a>
                                    @endfor
                                </div>
                            </details>
                        @endforeach
                    </div>
                </x-ff.card>
            </div>
        @endforeach
    </div>
    <p class="small text-ff-muted mt-3">{{ __(':name. Public domain text via bible-api.com.', ['name' => $translations[$translation]['name'] ?? '']) }}</p>
</x-layouts.member>
