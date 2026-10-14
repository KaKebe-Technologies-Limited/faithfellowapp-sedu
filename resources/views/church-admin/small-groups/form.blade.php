<x-layouts.church-admin :church="$church" :title="$group->exists ? __('Edit group') : __('New group')">
    <x-ff.page-header :title="$group->exists ? __('Edit group') : __('New group')"
        :breadcrumbs="[__('Small groups') => route('church-admin.small-groups.index', $church), ($group->exists ? $group->name : __('New')) => null]" />

    <form method="POST" action="{{ $group->exists ? route('church-admin.small-groups.update', [$church, $group]) : route('church-admin.small-groups.store', $church) }}" class="row g-4" novalidate>
        @csrf
        @if ($group->exists) @method('PUT') @endif
        <div class="col-lg-8">
            <div class="ff-card">
                <div class="row">
                    <div class="col-md-8"><x-ff.input name="name" :label="__('Group name')" :value="$group->name" required maxlength="120" :placeholder="__('e.g. Ireda Cell')" /></div>
                    <div class="col-md-4"><x-ff.select name="type" :label="__('Type')" :options="collect(\App\Models\SmallGroup::TYPES)->map(fn ($l) => __($l))" :value="$group->type" required /></div>
                </div>
                <x-ff.textarea name="description" :label="__('Description')" :value="$group->description" rows="3" maxlength="2000" />
                <div class="row">
                    <div class="col-md-6"><x-ff.select name="meeting_day" :label="__('Meets on')" :options="collect(\App\Models\ServiceTime::DAYS)->map(fn ($d) => __($d))" :value="$group->meeting_day" :placeholder="__('— Not set —')" /></div>
                    <div class="col-md-6"><x-ff.input type="time" name="meeting_time" :label="__('Time')" :value="$group->meeting_time ? substr($group->meeting_time, 0, 5) : null" /></div>
                </div>
                <x-ff.input name="location" :label="__('Where')" :value="$group->location" maxlength="255" :placeholder="__('e.g. Mama Grace’s home, Ireda')" />
                <div class="row">
                    <div class="col-6"><x-ff.input name="lat" :label="__('Latitude')" :value="$group->lat" inputmode="decimal" /></div>
                    <div class="col-6"><x-ff.input name="lng" :label="__('Longitude')" :value="$group->lng" inputmode="decimal" /></div>
                </div>
                <p class="form-text mt-n2">{{ __('Optional — shows the group on the members’ map. Tip: long-press a spot in Google Maps to copy its coordinates.') }}</p>
            </div>
        </div>
        <div class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <x-ff.select name="leader_id" :label="__('Leader')" :options="$people" :value="$group->leader_id" :placeholder="__('— Choose a member —')" :help="__('Leaders can take attendance and message the group.')" />
                <x-ff.select name="branch_id" :label="__('Branch')" :options="$branches" :value="$group->branch_id" :placeholder="__('— Main church —')" />
                <x-ff.input type="number" name="capacity" :label="__('Capacity')" :value="$group->capacity" min="2" max="1000" />
                <x-ff.checkbox name="is_open" :label="__('Open — members can ask to join')" :checked="$group->is_open" switch />
                <x-ff.select name="status" :label="__('Status')" :options="['active' => __('Active'), 'inactive' => __('Inactive')]" :value="$group->status" required />
            </div>
            <button class="btn btn-ff-primary btn-lg">{{ $group->exists ? __('Save changes') : __('Create group') }}</button>
        </div>
    </form>
</x-layouts.church-admin>
