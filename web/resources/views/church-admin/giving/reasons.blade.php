<x-layouts.church-admin :church="$church" :title="__('Giving reasons')">
    <x-ff.page-header :title="__('Finance')" :subtitle="__('What members can give for at your church.')" />
    @include('church-admin.giving._nav')

    <div class="row g-4">
        <div class="col-xl-8">
            <x-ff.card :title="__('Giving reasons')" :subtitle="__('Members choose one of these when they give. Only your church adds to this list.')" flush>
                <div class="table-responsive">
                    <table class="table ff-table mb-0">
                        <thead><tr><th>{{ __('Reason') }}</th><th>{{ __('Counts as') }}</th><th class="text-end">{{ __('Received') }}</th><th>{{ __('Shown to members') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($reasons as $reason)
                                <tr>
                                    <td style="min-width: 220px">
                                        <input form="reason-{{ $reason->id }}" name="name" value="{{ $reason->name }}" class="form-control form-control-sm fw-semibold" maxlength="60" required aria-label="{{ __('Name') }}">
                                        <input form="reason-{{ $reason->id }}" name="description" value="{{ $reason->description }}" class="form-control form-control-sm mt-1" maxlength="160" placeholder="{{ __('A short line members see (optional)') }}" aria-label="{{ __('Description') }}">
                                    </td>
                                    <td>
                                        <select form="reason-{{ $reason->id }}" name="category" class="form-select form-select-sm" style="min-width: 150px" aria-label="{{ __('Counts as') }}">
                                            @foreach ($categories as $key => $label)<option value="{{ $key }}" @selected($reason->category === $key)>{{ __($label) }}</option>@endforeach
                                        </select>
                                    </td>
                                    <td class="text-end small text-nowrap">{{ ff_money($totals[$reason->id]->total ?? 0) }}<div class="small-xs text-ff-muted">{{ trans_choice(':n gift|:n gifts', (int) ($totals[$reason->id]->gifts ?? 0), ['n' => (int) ($totals[$reason->id]->gifts ?? 0)]) }}</div></td>
                                    <td><div class="form-check form-switch"><input form="reason-{{ $reason->id }}" class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" @checked($reason->is_active) aria-label="{{ __('Shown to members') }}"></div></td>
                                    <td class="text-end text-nowrap">
                                        <button form="reason-{{ $reason->id }}" class="btn btn-sm btn-ff-soft">{{ __('Save') }}</button>
                                        <form method="POST" action="{{ route('church-admin.giving.reasons.destroy', [$church, $reason]) }}" class="d-inline" data-ff-confirm="{{ __('Remove this reason? Gifts already given keep its name.') }}">@csrf @method('DELETE')
                                            <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Remove') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-ff.empty-state icon="bi-heart" :title="__('No giving reasons yet')" :text="__('Add the first one on the right, for example Tithe or Building fund.')" class="border-0" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- The row fields belong to these forms by their form="…" attribute (a form cannot sit inside a table row). --}}
                @foreach ($reasons as $reason)
                    <form method="POST" action="{{ route('church-admin.giving.reasons.update', [$church, $reason]) }}" id="reason-{{ $reason->id }}" class="d-none">@csrf @method('PUT')</form>
                @endforeach
            </x-ff.card>
        </div>
        <div class="col-xl-4">
            <x-ff.card :title="__('Add a reason')">
                <form method="POST" action="{{ route('church-admin.giving.reasons.store', $church) }}" class="d-grid gap-3">@csrf
                    <div>
                        <label class="form-label small fw-semibold" for="reason-name">{{ __('Name') }}</label>
                        <input id="reason-name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" maxlength="60" required placeholder="{{ __('For example: Youth camp') }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="form-label small fw-semibold" for="reason-category">{{ __('Counts as') }}</label>
                        <select id="reason-category" name="category" class="form-select">
                            @foreach ($categories as $key => $label)<option value="{{ $key }}" @selected(old('category', 'offering') === $key)>{{ __($label) }}</option>@endforeach
                        </select>
                        <div class="form-text">{{ __('Used to group this reason with the platform’s standard reports.') }}</div>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold" for="reason-description">{{ __('A short line members see') }}</label>
                        <input id="reason-description" name="description" value="{{ old('description') }}" class="form-control" maxlength="160">
                    </div>
                    <button class="btn btn-ff-primary">{{ __('Add reason') }}</button>
                </form>
            </x-ff.card>
            <p class="small text-ff-muted mt-3">{{ __('Fundraising campaigns are separate: members donate to them from the campaign itself, and the records show them under “Fundraising”.') }}</p>
        </div>
    </div>
</x-layouts.church-admin>
