{{-- Ticked churches go to church_ids[]: a find box, then one row per church. --}}
<input type="search" id="church-filter" class="form-control form-control-sm mb-2" placeholder="{{ __('Find a church') }}" aria-label="{{ __('Find a church') }}">
<div class="list-group" style="max-height: 260px; overflow-y: auto" id="church-list">
    @forelse ($churches as $c)
        <label class="list-group-item d-flex align-items-center gap-2" data-church="{{ $c->id }}" data-name="{{ Str::lower($c->name.' '.$c->city) }}">
            <input class="form-check-input mt-0" type="checkbox" name="church_ids[]" value="{{ $c->id }}">
            <span class="flex-grow-1"><span class="fw-semibold text-ff-black">{{ $c->name }}</span>@if ($c->city)<span class="d-block small text-ff-muted">{{ $c->city }}</span>@endif</span>
            <span class="ff-badge ff-badge-success d-none" data-following>{{ __('Already follows') }}</span>
        </label>
    @empty
        <div class="list-group-item small text-ff-muted">{{ __('There are no active churches yet.') }}</div>
    @endforelse
</div>
@error('church_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
@once
    @push('scripts')
        <script>
            document.getElementById('church-filter')?.addEventListener('input', (event) => {
                const needle = event.target.value.trim().toLowerCase();
                document.querySelectorAll('#church-list [data-church]').forEach((row) => row.classList.toggle('d-none', needle !== '' && !row.dataset.name.includes(needle)));
            });
        </script>
    @endpush
@endonce
