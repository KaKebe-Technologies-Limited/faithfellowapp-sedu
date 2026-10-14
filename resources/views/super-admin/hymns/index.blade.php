<x-layouts.super-admin :title="__('Hymn book')">
    <x-ff.page-header :title="__('Hymn book')" :subtitle="__('The hymns members read in the app. Only you add hymnals and hymns here; every hymn needs its credits.')">
        <a href="{{ route('super-admin.hymnals.create') }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('New hymnal') }}</a>
    </x-ff.page-header>

    @if ($hymnals->isEmpty())
        <x-ff.empty-state icon="bi-journal-richtext" :title="__('No hymnals yet')" :message="__('Create a hymnal, for example Church of Uganda Hymnal in Luganda, then add its hymns one by one. Run the HymnSeeder to load the classic public-domain hymns.')" />
    @else
        <div class="row g-3">
            @foreach ($hymnals as $hymnal)
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('super-admin.hymnals.show', $hymnal) }}" class="ff-card text-decoration-none h-100 d-block">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold text-ff-black">{{ $hymnal->name }}</div>
                                <div class="small text-ff-muted">{{ $hymnal->languageLabel() }}@if ($hymnal->denomination) · {{ $hymnal->denomination }}@endif</div>
                            </div>
                            <x-ff.status :status="$hymnal->is_published ? 'published' : 'draft'" />
                        </div>
                        @if ($hymnal->description)<p class="small text-ff-muted mt-2 mb-2">{{ \Illuminate\Support\Str::limit($hymnal->description, 120) }}</p>@endif
                        <div class="small"><i class="bi bi-music-note-list text-ff-primary"></i> {{ trans_choice(':n hymn|:n hymns', $hymnal->hymns_count, ['n' => $hymnal->hymns_count]) }}</div>
                        @if ($hymnal->source_name)<div class="small-xs text-ff-muted mt-1">{{ __('Source: :s', ['s' => $hymnal->source_name]) }}</div>@endif
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.super-admin>
