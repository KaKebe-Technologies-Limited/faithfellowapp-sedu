<x-layouts.church-admin :church="$church" :title="$devotional->exists ? __('Edit devotional') : __('New devotional')">
    <x-ff.page-header :title="$devotional->exists ? __('Edit devotional') : __('New devotional')"
        :breadcrumbs="[__('Devotionals') => route('church-admin.devotionals.index', $church), ($devotional->exists ? $devotional->title : __('New')) => null]" />

    <form method="POST" enctype="multipart/form-data" action="{{ $devotional->exists ? route('church-admin.devotionals.update', [$church, $devotional]) : route('church-admin.devotionals.store', $church) }}" class="row g-4" novalidate>
        @csrf
        @if ($devotional->exists) @method('PUT') @endif
        <div class="col-lg-8">
            <div class="ff-card">
                <x-ff.input name="title" :label="__('Title')" :value="$devotional->title" required />
                <div class="row">
                    <div class="col-md-5"><x-ff.input name="scripture" :label="__('Scripture reference')" :value="$devotional->scripture" placeholder="Psalm 23:1" /></div>
                    <div class="col-md-7"><x-ff.input name="scripture_text" :label="__('Verse text')" :value="$devotional->scripture_text" :placeholder="__('The Lord is my shepherd; I shall not want.')" /></div>
                </div>
                <x-ff.textarea name="body" :label="__('Devotional')" :value="$devotional->body" editor required />
                <x-ff.textarea name="prayer" :label="__('Closing prayer')" :value="$devotional->prayer" rows="2" maxlength="1000" />
            </div>
        </div>
        <div class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <x-ff.input name="publish_date" type="date" :label="__('Publish date')" :value="$devotional->publish_date?->format('Y-m-d')" required />
                <x-ff.select name="status" :label="__('Status')" :options="['published' => __('Published'), 'draft' => __('Draft')]" :value="$devotional->status" />
                <x-ff.select name="visibility" :label="__('Who can see it')" :options="['public' => __('Everyone'), 'members' => __('Members only')]" :value="$devotional->visibility" />
                <button class="btn btn-ff-primary w-100">{{ __('Save devotional') }}</button>
            </div>
            <div class="ff-card">
                <h2 class="h6"><i class="bi bi-headphones"></i> {{ __('Audio devotional') }}</h2>
                <p class="small text-ff-muted">{{ __('Data-light: record 3–5 minutes at 16–32 kbps.') }}</p>
                <input type="file" name="audio" accept="audio/*" class="form-control mb-2 @error('audio') is-invalid @enderror" aria-label="{{ __('Audio file') }}">
                @error('audio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <x-ff.input name="audio_url" type="url" :label="__('…or audio URL')" :value="$devotional->audio_url" />
            </div>
        </div>
    </form>
</x-layouts.church-admin>
