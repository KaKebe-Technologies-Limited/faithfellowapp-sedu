{{--
    Slide-out notebook beside whatever the member is reading.
    Expects $context (e.g. "bible:MAT.3"), $reference (label). Optional $churchId.
--}}
@auth
<div x-data="noteTaker({ context: @js($context), reference: @js($reference), url: @js(request()->getRequestUri()), churchId: @js($churchId ?? null),
        loadUrl: @js(route('notes.context')), saveUrl: @js(route('notes.save')) })" x-cloak>
    <button type="button" class="ff-notes-fab" x-show="!open" @click="open = true" aria-label="{{ __('Open my notes') }}">
        <i class="bi bi-journal-text"></i> {{ __('Notes') }} <span class="dot" x-show="body.length" aria-hidden="true"></span>
    </button>

    <div class="ff-notes-backdrop" x-show="open" x-transition.opacity @click="open = false"></div>
    <aside class="ff-notes-panel" x-show="open" :data-color="color" role="dialog" aria-label="{{ __('My notes') }}"
        x-transition.opacity @keydown.escape.window="open = false">
        <div class="ff-notes-head">
            <div class="d-flex align-items-center gap-2">
                <span class="ff-notes-ref"><i class="bi bi-journal-text"></i> {{ $reference }}</span>
                <a :href="noteUrl" x-show="noteUrl" class="ms-auto small" title="{{ __('Open in notebook') }}"><i class="bi bi-arrows-angle-expand"></i></a>
                <button type="button" class="btn-close" :class="noteUrl ? '' : 'ms-auto'" @click="open = false" aria-label="{{ __('Close notes') }}"></button>
            </div>
            <input class="ff-notes-title" x-model="title" @input="changed()" maxlength="160" placeholder="{{ __('Title your note…') }}" aria-label="{{ __('Note title') }}">
        </div>
        <div class="ff-notes-tools" role="toolbar" aria-label="{{ __('Formatting') }}">
            <button type="button" @click="format('heading')" title="{{ __('Heading') }}"><i class="bi bi-type-h1"></i></button>
            <button type="button" @click="format('bullet')" title="{{ __('Bullet') }}"><i class="bi bi-list-ul"></i></button>
            <button type="button" @click="format('quote')" title="{{ __('Quote') }}"><i class="bi bi-quote"></i></button>
            <button type="button" @click="format('time')" title="{{ __('Time stamp') }}"><i class="bi bi-clock"></i></button>
            <span class="ms-auto small text-ff-muted align-self-center" x-show="!loaded"><span class="spinner-border spinner-border-sm"></span></span>
        </div>
        <textarea class="ff-notes-body" x-ref="body" x-model="body" @input="changed()" :disabled="!loaded"
            placeholder="{{ __('What is God saying to you? Tap a verse and choose “Note” to quote it here.') }}" aria-label="{{ __('Note') }}"></textarea>
        <div class="ff-notes-foot">
            <i class="bi" :class="status === 'error' ? 'bi-wifi-off text-danger' : (status === 'saved' || savedAt ? 'bi-cloud-check text-success' : 'bi-cloud')"></i>
            <span x-text="statusLabel"></span>
            <div class="ms-auto d-flex gap-1" role="radiogroup" aria-label="{{ __('Paper colour') }}">
                @foreach (\App\Models\Note::COLORS as $key => $hex)
                    <span class="ff-notes-swatch" role="radio" tabindex="0" style="background: {{ $hex }}" :class="color === '{{ $key }}' && 'active'"
                        :aria-checked="color === '{{ $key }}'" @click="color = '{{ $key }}'; changed()" @keydown.enter="color = '{{ $key }}'; changed()" aria-label="{{ ucfirst($key) }}"></span>
                @endforeach
            </div>
        </div>
    </aside>
</div>
@endauth
