<x-layouts.member :title="$passage['title']">
    <div class="ff-bible-page">
        <div class="ff-bible-toolbar">
            <a href="{{ route('bible.index') }}" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('All books') }}"><i class="bi bi-grid-3x3-gap"></i></a>
            <span class="fw-bold small">{{ $passage['title'] }}</span>
            <form method="GET" class="m-0 ms-auto">
                <input type="hidden" name="ref" value="{{ $reference }}">
                <select name="t" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Translation') }}">
                    @foreach ($translations as $key => $t)<option value="{{ $key }}" @selected($translation === $key)>{{ $t['short'] }}</option>@endforeach
                </select>
            </form>
        </div>
        <header class="ff-scripture-heading">
            <span class="ff-eyebrow">{{ $translations[$translation]['name'] ?? '' }}</span>
            <h1 class="h2">{{ $passage['title'] }}</h1>
        </header>
        @include('member.bible._passage')
    </div>

    @include('partials.note-taker', ['context' => 'bible:'.\Illuminate\Support\Str::slug($passage['title'], '.'), 'reference' => $passage['title']])
</x-layouts.member>
