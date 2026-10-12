<x-layouts.member :title="$note->displayTitle()">
    <x-ff.page-header :title="$note->displayTitle()" :breadcrumbs="[__('My notes') => route('notes.index'), Str::limit($note->displayTitle(), 30) => null]">
        @if ($note->url)<a href="{{ $note->url }}" class="btn btn-ff-soft"><i class="bi bi-box-arrow-up-right"></i> {{ __('Back to :ref', ['ref' => Str::limit($note->reference ?: __('source'), 28)]) }}</a>@endif
        <form method="POST" action="{{ route('notes.destroy', $note) }}" data-ff-confirm="{{ __('Delete this note?') }}">@csrf @method('DELETE')
            <button class="btn btn-ff-ghost text-danger"><i class="bi bi-trash"></i></button>
        </form>
    </x-ff.page-header>

    <div class="row g-4" style="max-width: 1100px"
        x-data="noteTaker({ context: @js($note->context), reference: @js($note->reference), url: @js($note->url), churchId: @js($note->church_id), loadUrl: @js(route('notes.context')), saveUrl: @js(route('notes.save')), inline: true })">
        <div class="col-lg-7">
            <div class="ff-card p-0 overflow-hidden" :style="`background: ${ {{ Js::from(\App\Models\Note::COLORS) }}[color] }`">
                <div class="ff-notes-head">
                    @if ($note->reference)<span class="ff-notes-ref"><i class="bi bi-journal-text"></i> {{ $note->reference }}</span>@endif
                    <input class="ff-notes-title" x-model="title" @input="changed()" maxlength="160" placeholder="{{ __('Title your note…') }}" aria-label="{{ __('Note title') }}">
                </div>
                <div class="ff-notes-tools" role="toolbar" aria-label="{{ __('Formatting') }}">
                    <button type="button" @click="format('heading')" title="{{ __('Heading') }}"><i class="bi bi-type-h1"></i></button>
                    <button type="button" @click="format('bullet')" title="{{ __('Bullet') }}"><i class="bi bi-list-ul"></i></button>
                    <button type="button" @click="format('quote')" title="{{ __('Quote') }}"><i class="bi bi-quote"></i></button>
                    <button type="button" @click="format('time')" title="{{ __('Time stamp') }}"><i class="bi bi-clock"></i></button>
                </div>
                <textarea class="ff-notes-body w-100" style="min-height: 480px" x-ref="body" x-model="body" @input="changed()" aria-label="{{ __('Note') }}"></textarea>
                <div class="ff-notes-foot">
                    <i class="bi" :class="status === 'error' ? 'bi-wifi-off text-danger' : 'bi-cloud-check text-success'"></i>
                    <span x-text="statusLabel"></span>
                    <div class="ms-auto d-flex gap-1">
                        @foreach (\App\Models\Note::COLORS as $key => $hex)
                            <span class="ff-notes-swatch" style="background: {{ $hex }}" :class="color === '{{ $key }}' && 'active'" @click="color = '{{ $key }}'; changed()" role="button" aria-label="{{ ucfirst($key) }}"></span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <aside class="col-lg-5">
            <div class="ff-card">
                <div class="small text-uppercase text-ff-muted fw-semibold mb-2">{{ __('Preview') }}</div>
                <div class="ff-note-render">{!! $note->renderBody() !!}</div>
                <p class="small text-ff-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> {{ __('Tip: start a line with “# ” for a heading, “- ” for a bullet or “> ” for a quote. **bold** and *italic* work too.') }}</p>
            </div>
        </aside>
    </div>
</x-layouts.member>
