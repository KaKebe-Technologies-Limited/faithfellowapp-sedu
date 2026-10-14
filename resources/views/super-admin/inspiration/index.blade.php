<x-layouts.super-admin :title="__('Inspiration')">
    <x-ff.page-header :title="__('Inspiration')" :subtitle="__('Short, powerful messages the app shows on the home screen in the morning, mid-morning and evening. Each part of the day shows one a day.')" />

    @if (session('status'))<div class="alert alert-success py-2 small">{{ session('status') }}</div>@endif

    <div class="row g-3 mb-4">
        @foreach (\App\Models\Inspiration::SLOTS as $key => $label)
            <div class="col-md-4"><div class="ff-card h-100">
                <div class="text-ff-muted small">{{ __($label) }}</div>
                <div class="fs-4 fw-semibold">{{ $counts[$key] ?? 0 }} <span class="fs-6 text-ff-muted">{{ __('messages on') }}</span></div>
                @if ($now && $now['slot'] === $key)<span class="ff-badge ff-badge-success mt-2"><i class="bi bi-broadcast"></i> {{ __('On the home screen now') }}</span>@endif
            </div></div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('super-admin.inspiration.store') }}" class="ff-card mb-4">
        @csrf
        <h2 class="h6 mb-3">{{ __('Add a message') }}</h2>
        <div class="row g-2">
            <div class="col-md-3"><x-ff.select name="slot" :label="__('Part of the day')" :options="collect(\App\Models\Inspiration::SLOTS)->map(fn ($l) => __($l))->all()" :value="old('slot', 'morning')" required /></div>
            <div class="col-md-6"><x-ff.input name="body" :label="__('Message (keep it brief)')" :value="old('body')" required :help="__('Up to 280 characters.')" /></div>
            <div class="col-md-3"><x-ff.input name="reference" :label="__('Bible reference (optional)')" :value="old('reference')" :help="__('Only for a word-for-word quotation.')" /></div>
        </div>
        <button class="btn btn-ff-primary mt-1"><i class="bi bi-plus-lg"></i> {{ __('Add') }}</button>
    </form>

    <div class="ff-table-wrap">
        <div class="ff-table-toolbar">
            <div class="ff-pills">
                <a href="{{ route('super-admin.inspiration.index') }}" @class(['ff-pill', 'active' => ! $slot])>{{ __('All') }}</a>
                @foreach (\App\Models\Inspiration::SLOTS as $key => $label)
                    <a href="{{ route('super-admin.inspiration.index', ['slot' => $key]) }}" @class(['ff-pill', 'active' => $slot === $key])>{{ __($label) }}</a>
                @endforeach
            </div>
        </div>
        <div class="table-responsive">
            <table class="table ff-table align-middle">
                <thead><tr><th>{{ __('Part of the day') }}</th><th>{{ __('Message') }}</th><th>{{ __('On') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($messages as $m)
                        <tr>
                            <td colspan="4" class="p-0">
                                <form method="POST" action="{{ route('super-admin.inspiration.update', $m) }}" class="d-flex flex-wrap gap-2 align-items-center p-2">
                                    @csrf @method('PUT')
                                    <select name="slot" class="form-select form-select-sm" style="width:auto">
                                        @foreach (\App\Models\Inspiration::SLOTS as $key => $label)<option value="{{ $key }}" @selected($m->slot === $key)>{{ __($label) }}</option>@endforeach
                                    </select>
                                    <input name="body" value="{{ $m->body }}" class="form-control form-control-sm flex-grow-1" style="min-width:260px" maxlength="280" required aria-label="{{ __('Message') }}">
                                    <input name="reference" value="{{ $m->reference }}" class="form-control form-control-sm" style="width:150px" maxlength="80" placeholder="{{ __('Reference') }}" aria-label="{{ __('Bible reference') }}">
                                    <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" @checked($m->is_active) aria-label="{{ __('On') }}"></div>
                                    <button class="btn btn-sm btn-ff-soft">{{ __('Save') }}</button>
                                    <button class="btn btn-sm btn-outline-danger" form="del-{{ $m->id }}" onclick="return confirm('{{ __('Remove this message?') }}')"><i class="bi bi-trash"></i></button>
                                </form>
                                <form id="del-{{ $m->id }}" method="POST" action="{{ route('super-admin.inspiration.destroy', $m) }}" class="d-none">@csrf @method('DELETE')</form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.super-admin>
