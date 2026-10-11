<x-layouts.church-admin :church="$church" :title="$role->exists ? __('Edit role') : __('New role')">
    <x-ff.page-header :title="$role->exists ? __('Edit role: :name', ['name' => $role->name]) : __('Create a custom role')"
        :breadcrumbs="[__('Staff & roles') => route('church-admin.roles.index', $church), ($role->exists ? $role->name : __('New role')) => null]" />

    @php($granted = old('permissions', $role->permissions ?? []))
    <form method="POST" action="{{ $role->exists ? route('church-admin.roles.update', [$church, $role]) : route('church-admin.roles.store', $church) }}"
          class="row g-4" x-data="permissionMatrix()" novalidate>
        @csrf
        @if ($role->exists) @method('PUT') @endif
        <div class="col-lg-4">
            <div class="ff-card">
                <x-ff.input name="name" :label="__('Role name')" :value="$role->name" required />
                <x-ff.textarea name="description" :label="__('Description')" :value="$role->description" rows="3" />
                @unless ($role->exists)
                    <label class="form-label">{{ __('Start from a preset') }}</label>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach ($presets as $presetName => $presetPerms)
                            <button type="button" class="btn btn-sm btn-ff-soft" @click="toggleAll('.perm', false); @js($presetPerms).forEach(p => { const el = $root.querySelector('#perm_' + p.replace('.', '_')); if (el) el.checked = true; })">{{ $presetName }}</button>
                        @endforeach
                    </div>
                @endunless
                <button class="btn btn-ff-primary w-100">{{ __('Save role') }}</button>
                @if ($role->exists && ! $role->is_preset)
                    <hr>
                    <button type="submit" form="deleteRole" class="btn btn-ff-ghost text-danger w-100"><i class="bi bi-trash"></i> {{ __('Delete role') }}</button>
                @endif
            </div>
        </div>
        <div class="col-lg-8">
            <div class="ff-table-wrap">
                <div class="ff-table-toolbar">
                    <h2 class="h6 mb-0">{{ __('Permissions') }}</h2>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-ff-ghost" @click="toggleAll('.perm', true)">{{ __('Select all') }}</button>
                        <button type="button" class="btn btn-sm btn-ff-ghost" @click="toggleAll('.perm', false)">{{ __('Clear') }}</button>
                    </div>
                </div>
                @error('permissions.*')<div class="alert alert-danger m-3 small">{{ $message }}</div>@enderror
                <table class="table ff-table ff-matrix mb-0">
                    <tbody>
                        @foreach ($catalog as $group => $permissions)
                            <tr class="table-light">
                                <th class="text-start text-uppercase small" colspan="2">
                                    {{ __($group) }}
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-2 small" @click="toggleAll('.perm-{{ Str::slug($group) }}', true)">{{ __('all') }}</button>
                                </th>
                            </tr>
                            @foreach ($permissions as $key => $label)
                                <tr>
                                    <td class="text-start">
                                        <label for="perm_{{ str_replace('.', '_', $key) }}" class="d-block">
                                            <span class="fw-semibold text-ff-black">{{ __($label) }}</span>
                                            <code class="small-xs d-block text-ff-muted">{{ $key }}</code>
                                        </label>
                                    </td>
                                    <td style="width: 80px">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input perm perm-{{ Str::slug($group) }}" type="checkbox" role="switch" name="permissions[]" value="{{ $key }}" id="perm_{{ str_replace('.', '_', $key) }}" @checked(in_array($key, $granted, true))>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </form>
    @if ($role->exists && ! $role->is_preset)
        <form method="POST" action="{{ route('church-admin.roles.destroy', [$church, $role]) }}" id="deleteRole" data-ff-confirm="{{ __('Delete this role?') }}">@csrf @method('DELETE')</form>
    @endif
</x-layouts.church-admin>
