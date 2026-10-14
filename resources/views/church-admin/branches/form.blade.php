<x-layouts.church-admin :church="$church" :title="$branch->exists ? __('Edit branch') : __('Add branch')">
    <x-ff.page-header :title="$branch->exists ? __('Edit :name', ['name' => $branch->name]) : __('Add a branch')"
        :breadcrumbs="[__('Branches') => route('church-admin.branches.index', $church), ($branch->exists ? $branch->name : __('New')) => null]" />

    <form method="POST" action="{{ $branch->exists ? route('church-admin.branches.update', [$church, $branch]) : route('church-admin.branches.store', $church) }}" class="ff-card" style="max-width: 760px" novalidate>
        @csrf
        @if ($branch->exists) @method('PUT') @endif
        <x-ff.input name="name" :label="__('Branch name')" :value="$branch->name" required />
        <div class="row">
            <div class="col-md-6"><x-ff.input name="city" :label="__('City / town')" :value="$branch->city" /></div>
            <div class="col-md-6"><x-ff.input name="phone" :label="__('Phone')" :value="$branch->phone" /></div>
            <div class="col-12"><x-ff.input name="address" :label="__('Address')" :value="$branch->address" /></div>
            <div class="col-6"><x-ff.input name="lat" type="number" step="any" :label="__('Latitude')" :value="$branch->lat" /></div>
            <div class="col-6"><x-ff.input name="lng" type="number" step="any" :label="__('Longitude')" :value="$branch->lng" /></div>
            <div class="col-12"><x-ff.input name="leader_name" :label="__('Branch leader / pastor')" :value="$branch->leader_name" /></div>
        </div>
        <x-ff.checkbox name="is_main" :label="__('This is the main campus')" :checked="$branch->is_main" switch />
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-ff-primary px-4">{{ __('Save branch') }}</button>
            <a href="{{ route('church-admin.branches.index', $church) }}" class="btn btn-ff-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.church-admin>
