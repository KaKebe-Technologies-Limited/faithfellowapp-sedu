<x-layouts.member :title="__('My notes')">
    <x-ff.page-header :title="__('My notes')" :subtitle="__('Everything you jotted down while reading the Bible, following a plan or listening to a sermon. Only you can see these.')">
        <a href="{{ route('notes.export') }}" class="btn btn-ff-soft"><i class="bi bi-download"></i> {{ __('Export') }}</a>
        <form method="POST" action="{{ route('notes.create') }}">@csrf
            <button class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('New note') }}</button>
        </form>
    </x-ff.page-header>

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-4">
        <nav class="ff-pills" aria-label="{{ __('Filter') }}">
            <a href="{{ route('notes.index', array_filter(['q' => request('q')])) }}" @class(['ff-pill', 'active' => ! request('kind')])>{{ __('All') }} <span class="opacity-75">{{ $counts->sum() }}</span></a>
            @foreach (\App\Models\Note::KINDS as $kind => [$label, $icon])
                @if ($counts[$kind] ?? 0)
                    <a href="{{ route('notes.index', array_filter(['kind' => $kind, 'q' => request('q')])) }}" @class(['ff-pill', 'active' => request('kind') === $kind])><i class="bi {{ $icon }}"></i> {{ __($label) }} <span class="opacity-75">{{ $counts[$kind] }}</span></a>
                @endif
            @endforeach
        </nav>
        <form method="GET" class="d-flex gap-2" role="search">
            @if (request('kind'))<input type="hidden" name="kind" value="{{ request('kind') }}">@endif
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Search your notes…') }}" aria-label="{{ __('Search notes') }}">
        </form>
    </div>

    @if ($notes->isEmpty())
        <x-ff.empty-state icon="bi-journal-text" :title="request('q') ? __('No notes match your search') : __('Your notebook is empty')"
            :message="__('Open the Bible or a reading plan and tap “Notes” at the bottom right — everything you write saves here automatically.')">
            <a href="{{ route('bible.index') }}" class="btn btn-ff-primary mt-2"><i class="bi bi-book"></i> {{ __('Open the Bible') }}</a>
        </x-ff.empty-state>
    @else
        <div class="row g-3">
            @foreach ($notes as $note)
                @php([$label, $icon] = \App\Models\Note::KINDS[$note->kind()])
                <div class="col-md-6 col-xl-4">
                    <article class="ff-note-card" style="background: {{ \App\Models\Note::COLORS[$note->color] ?? '#FFFDF7' }}">
                        <div class="d-flex align-items-center gap-2 small">
                            <span class="ff-tag"><i class="bi {{ $icon }}"></i> {{ __($label) }}</span>
                            @if ($note->church)<span class="text-ff-muted text-truncate">{{ $note->church->name }}</span>@endif
                            <form method="POST" action="{{ route('notes.pin', $note) }}" class="ms-auto">@csrf
                                <button class="btn btn-link btn-sm p-0 {{ $note->is_pinned ? 'text-ff-primary' : 'text-ff-muted' }}" aria-label="{{ $note->is_pinned ? __('Unpin') : __('Pin') }}"><i class="bi {{ $note->is_pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle' }}"></i></button>
                            </form>
                        </div>
                        <h2 class="h6 mb-0"><a href="{{ route('notes.show', $note) }}" class="text-ff-black stretched-link">{{ $note->displayTitle() }}</a></h2>
                        @if ($note->reference && $note->title)<div class="small text-ff-primary fw-semibold">{{ $note->reference }}</div>@endif
                        <div class="ff-note-excerpt">{{ Str::limit(trim(preg_replace(['/^\s*[#>\-•]\s*/m', '/\*{1,2}([^*]+)\*{1,2}/', '/\R{2,}/'], ['', '$1', "\n"], (string) $note->body)), 320) ?: __('Empty note') }}</div>
                        <div class="small-xs text-ff-muted mt-auto">{{ __('Edited :ago', ['ago' => $note->updated_at->diffForHumans()]) }}</div>
                    </article>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $notes->links() }}</div>
    @endif
</x-layouts.member>
