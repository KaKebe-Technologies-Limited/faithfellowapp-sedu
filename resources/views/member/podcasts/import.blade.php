<x-layouts.member :title="__('Import a podcast')">
    <x-ff.page-header :title="__('Import your podcast')" :subtitle="__('Already publishing on Podbean, Buzzsprout or another host? Paste its RSS feed link and we bring in the name, the cover and every episode. The audio stays on your host.')"
        :breadcrumbs="[__('My podcasts') => route('podcasts.index'), __('Import') => null]" />

    <form method="POST" action="{{ route('podcasts.import.store') }}" class="ff-card" novalidate>
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">
                <x-ff.input name="feed_url" type="url" :label="__('RSS feed link')" :value="old('feed_url')" required placeholder="https://feed.podbean.com/yourname/feed.xml"
                    :help="__('On Podbean it is under Settings, then Feed. It starts with https and usually ends in feed.xml.')" />
                <x-ff.input name="host_name" :label="__('Presented by')" :value="old('host_name', $hostName)" required :help="__('The name listeners see (yours, or your ministry’s).')" />
                <x-ff.select name="category" :label="__('Category (optional)')" :options="['' => __('Choose for me')] + collect(\App\Models\Podcast::CATEGORIES)->map(fn ($l) => __($l))->all()" :value="old('category', '')" />
            </div>
        </div>

        <div class="alert alert-light border mt-3">
            <x-ff.checkbox name="faith_based" :label="__('My podcast is faith-based, and I have the right to share its audio.')" :checked="false" />
            <div class="small text-ff-muted">{{ __('We read every podcast before it appears in the app. New episodes you publish on your host are added automatically.') }}</div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-ff-primary"><i class="bi bi-cloud-download"></i> {{ __('Import my podcast') }}</button>
            <a href="{{ route('podcasts.index') }}" class="btn btn-ff-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.member>
