<x-layouts.church-admin :church="$church" :title="__('Branding')">
    <x-ff.page-header :title="__('Settings')" />
    @include('church-admin.settings._nav')

    <form method="POST" action="{{ route('church-admin.settings.branding.update', $church) }}" enctype="multipart/form-data" class="ff-card" style="max-width: 900px" novalidate>
        @csrf
        <h2 class="h5">{{ __('Branding') }}</h2>
        <p class="text-ff-muted small">{{ __('Your logo and cover appear on your church page, website, receipts and in members’ feeds.') }}</p>
        @include('church-admin.settings._branding-fields')
        <button class="btn btn-ff-primary px-4 mt-4">{{ __('Save branding') }}</button>
    </form>
</x-layouts.church-admin>
