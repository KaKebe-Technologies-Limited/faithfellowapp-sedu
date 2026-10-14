@php($editing = $podcast->exists)
<x-layouts.member :title="$editing ? __('Edit podcast') : __('New podcast')">
    <x-ff.page-header :title="$editing ? __('Edit podcast') : __('Start your podcast')" :subtitle="__('This is what listeners see in the app. Keep it clear and welcoming.')"
        :breadcrumbs="[__('My podcasts') => route('podcasts.index'), ($editing ? $podcast->name : __('New')) => null]" />

    <form method="POST" enctype="multipart/form-data" class="ff-card" novalidate
        action="{{ $editing ? route('podcasts.update', $podcast) : route('podcasts.store') }}">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-ff.input name="name" :label="__('Podcast name')" :value="$podcast->name" required :help="__('For example: Morning Manna, Lira Youth Talk.')" />
                <x-ff.input name="host_name" :label="__('Presented by')" :value="$podcast->host_name" required :help="__('The name listeners see (yours, or your ministry’s).')" />
                <x-ff.textarea name="summary" :label="__('Short summary')" :value="$podcast->summary" rows="3" required :help="__('Two or three sentences, 20 to 500 characters. This shows under the name in the app.')" />
                <x-ff.textarea name="description" :label="__('About this podcast (optional)')" :value="$podcast->description" rows="6" />
                <div class="row">
                    <div class="col-md-6"><x-ff.select name="category" :label="__('Category')" :options="collect(\App\Models\Podcast::CATEGORIES)->map(fn ($l) => __($l))->all()" :value="$podcast->category ?: 'teaching'" required /></div>
                    <div class="col-md-6"><x-ff.select name="language" :label="__('Language')" :options="config('faithfellows.languages')" :value="$podcast->language ?: 'en'" required /></div>
                </div>
                <x-ff.input name="website_url" type="url" :label="__('Website (optional)')" :value="$podcast->website_url" placeholder="https://" />
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="cover">{{ __('Cover picture') }}</label>
                <div class="mb-2"><img src="{{ $podcast->exists ? $podcast->coverUrl() : asset('images/icon-192.png') }}" alt="" class="rounded-3 w-100" style="max-width:240px;aspect-ratio:1;object-fit:cover"></div>
                <input type="file" name="cover" id="cover" class="form-control @error('cover') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                @error('cover')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('A square picture, JPG or PNG, up to 4 MB. If you skip it, a picture is drawn for you.') }}</div>
            </div>
        </div>

        @unless ($editing)
            <div class="alert alert-light border mt-3">
                <x-ff.checkbox name="faith_based" :label="__('My podcast is faith-based, and I have the right to share its audio.')" :checked="false" />
                <div class="small text-ff-muted">{{ __('We read every podcast before it appears in the app. Phone numbers and emails are not allowed in the text: listeners reach you through the app.') }}</div>
            </div>
        @endunless

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-ff-primary">{{ $editing ? __('Save') : __('Send for review') }}</button>
            <a href="{{ $editing ? route('podcasts.show', $podcast) : route('podcasts.index') }}" class="btn btn-ff-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.member>
