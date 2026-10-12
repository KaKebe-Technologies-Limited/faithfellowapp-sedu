{{-- Floating actions for the verses a reader tapped (inside a bibleReader component). --}}
<div class="ff-verse-bar" x-show="selected.length" x-transition.opacity x-cloak role="toolbar" aria-label="{{ __('Selected verses') }}">
    <div class="ff-verse-bar-top">
        <span class="fw-semibold text-truncate" x-text="label"></span>
        <button type="button" class="btn btn-sm ms-auto" @click="clear()" aria-label="{{ __('Clear selection') }}"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="ff-verse-bar-actions">
        <div class="ff-hl-swatches" role="group" aria-label="{{ __('Highlight') }}">
            @foreach (\App\Models\BibleHighlight::COLORS as $key => [$name, $swatch])
                <button type="button" class="ff-hl-swatch" style="--sw: {{ $swatch }}" @click="highlight('{{ $key }}')" :class="allColored('{{ $key }}') && 'is-on'" title="{{ __($name) }}" aria-label="{{ __('Highlight :color', ['color' => __($name)]) }}"></button>
            @endforeach
            <button type="button" class="ff-hl-swatch is-clear" x-show="anyColored()" @click="highlight(null)" title="{{ __('Remove highlight') }}" aria-label="{{ __('Remove highlight') }}"><i class="bi bi-eraser"></i></button>
        </div>
        <button type="button" class="btn btn-sm" @click="copy()"><i class="bi bi-copy"></i> <span>{{ __('Copy') }}</span></button>
        <button type="button" class="btn btn-sm" @click="note()"><i class="bi bi-journal-plus"></i> <span>{{ __('Note') }}</span></button>
        <button type="button" class="btn btn-sm" @click="share()"><i class="bi bi-share"></i> <span>{{ __('Share') }}</span></button>
    </div>
</div>
