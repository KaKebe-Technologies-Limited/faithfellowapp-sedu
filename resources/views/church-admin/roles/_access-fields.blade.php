@php($suffix = $member?->id ?? 'new')
<label class="form-label">{{ __('Access level') }}</label>
<div class="d-grid gap-2 mb-3">
    <label class="ff-method-option">
        <input type="radio" class="form-check-input mt-0" name="access" value="admin" x-model="access">
        <span><strong class="text-ff-black d-block">{{ __('Church admin') }}</strong><span class="small text-ff-muted">{{ __('Full access including finance, staff and settings.') }}</span></span>
    </label>
    <label class="ff-method-option">
        <input type="radio" class="form-check-input mt-0" name="access" value="role" x-model="access">
        <span><strong class="text-ff-black d-block">{{ __('Specific role') }}</strong><span class="small text-ff-muted">{{ __('Editor, Finance, Media, Events, Moderator or a custom role.') }}</span></span>
    </label>
</div>
<div x-show="access === 'role'">
    <x-ff.select name="church_role_id" :id="'role_'.$suffix" :label="__('Role')" :options="$roles->pluck('name', 'id')" :value="$member?->church_role_id" :placeholder="__('Choose a role…')" />
</div>
@if ($branches->count() > 1)
    <x-ff.select name="branch_id" :id="'branch_'.$suffix" :label="__('Limit to branch')" :options="$branches" :value="$member?->branch_id" :placeholder="__('All branches')" :help="__('Branch-level admins only manage their branch.')" />
@endif
