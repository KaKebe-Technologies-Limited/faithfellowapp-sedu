<x-layouts.super-admin :title="__('Database updates')">
    <x-ff.page-header :title="__('Database updates')" :subtitle="__('When a new version of FaithFellows comes with changes to the database, apply them here. Nothing is deleted.')" />

    @if (session('status'))<div class="alert alert-success py-2 small">{{ session('status') }}</div>@endif
    @if (session('output'))<pre class="small bg-light border rounded p-3 mb-4">{{ session('output') }}</pre>@endif

    <div class="ff-card">
        @if ($pending === [])
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-check-circle-fill text-success fs-3"></i>
                <div>
                    <div class="fw-semibold">{{ __('The database is up to date.') }}</div>
                    <div class="small text-ff-muted">{{ __('There is nothing to apply.') }}</div>
                </div>
            </div>
        @else
            <p class="mb-2"><strong>{{ trans_choice(':count change is waiting|:count changes are waiting', count($pending), ['count' => count($pending)]) }}</strong></p>
            <ul class="small text-ff-muted">
                @foreach ($pending as $name)<li><code>{{ $name }}</code></li>@endforeach
            </ul>
            <p class="small text-ff-muted">{{ __('Until they are applied, some new screens may not work. It takes a few seconds. For a risky update, export the database in phpMyAdmin first.') }}</p>
            <form method="POST" action="{{ route('super-admin.updates.apply') }}" onsubmit="return confirm('{{ __('Apply the database changes now?') }}')">
                @csrf
                <button class="btn btn-ff-primary"><i class="bi bi-database-check"></i> {{ __('Apply now') }}</button>
            </form>
        @endif
    </div>
</x-layouts.super-admin>
