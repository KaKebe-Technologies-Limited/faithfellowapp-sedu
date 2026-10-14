@php($editing = $hymnal->exists)
<x-layouts.super-admin :title="$editing ? __('Edit hymnal') : __('New hymnal')">
    <x-ff.page-header :title="$editing ? __('Edit hymnal') : __('New hymnal')" :breadcrumbs="[__('Hymn book') => route('super-admin.hymnals.index'), ($editing ? $hymnal->name : __('New')) => null]" />

    <form method="POST" class="ff-card" novalidate action="{{ $editing ? route('super-admin.hymnals.update', $hymnal) : route('super-admin.hymnals.store') }}">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-ff.input name="name" :label="__('Hymnal name')" :value="$hymnal->name" required :help="__('For example: Church of Uganda Hymnal, Nyimbo za Kristo.')" />
                <div class="row">
                    <div class="col-md-6"><x-ff.select name="language" :label="__('Language')" :options="\App\Models\Hymnal::LANGUAGES" :value="$hymnal->language ?: 'en'" required /></div>
                    <div class="col-md-6"><x-ff.input name="denomination" :label="__('Denomination (optional)')" :value="$hymnal->denomination" :help="__('Anglican, Catholic, Pentecostal, SDA…')" /></div>
                </div>
                <x-ff.textarea name="description" :label="__('About this hymnal')" :value="$hymnal->description" rows="3" />
                <div class="row">
                    <div class="col-md-6"><x-ff.input name="source_name" :label="__('Where the texts come from')" :value="$hymnal->source_name" :help="__('Shown as a credit: a publisher, a church or a website.')" /></div>
                    <div class="col-md-6"><x-ff.input name="source_url" type="url" :label="__('Source link (optional)')" :value="$hymnal->source_url" placeholder="https://" /></div>
                </div>
            </div>
            <div class="col-lg-4">
                <x-ff.input name="sort" type="number" :label="__('Order in the app')" :value="$hymnal->sort ?? 0" :help="__('Smaller numbers come first.')" />
                <x-ff.checkbox name="is_published" :label="__('Show this hymnal in the app')" :checked="$hymnal->is_published" switch />
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-ff-primary">{{ $editing ? __('Save') : __('Create hymnal') }}</button>
            <a href="{{ $editing ? route('super-admin.hymnals.show', $hymnal) : route('super-admin.hymnals.index') }}" class="btn btn-ff-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.super-admin>
