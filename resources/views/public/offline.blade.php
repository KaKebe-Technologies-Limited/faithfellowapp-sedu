<x-layouts.public :title="__('Offline sermons')">
    <section class="ff-page-hero">
        <div class="container" style="max-width: 820px">
            <span class="ff-eyebrow">{{ __('Offline library') }}</span>
            <h1 class="mt-3">{{ __('Sermons saved on this device') }}</h1>
            <p class="ff-lead mb-0">{{ __('Listen without data. Saved sermons stay on this phone until you remove them.') }}</p>
        </div>
    </section>
    <section class="ff-section pt-4">
        <div class="container" style="max-width: 820px">
            <div data-ff-offline-list>
                <x-ff.empty-state icon="bi-download" :title="__('No sermons saved yet')" :message="__('Open any sermon and tap “Save offline” to keep it on this device.')" />
            </div>
        </div>
    </section>
</x-layouts.public>
