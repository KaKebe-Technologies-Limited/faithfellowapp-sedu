@php($editing = $hymn->exists)
<x-layouts.super-admin :title="$editing ? __('Edit hymn') : __('Add a hymn')">
    <x-ff.page-header :title="$editing ? $hymn->title : __('Add a hymn')" :subtitle="$hymnal->name" :breadcrumbs="[__('Hymn book') => route('super-admin.hymnals.index'), $hymnal->name => route('super-admin.hymnals.show', $hymnal), ($editing ? $hymn->title : __('New hymn')) => null]" />

    <form method="POST" class="ff-card" novalidate
        action="{{ $editing ? route('super-admin.hymns.update', [$hymnal, $hymn]) : route('super-admin.hymns.store', $hymnal) }}">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="row">
                    <div class="col-4"><x-ff.input name="number" type="number" :label="__('Hymn number')" :value="$hymn->number" /></div>
                    <div class="col-8"><x-ff.input name="title" :label="__('Title')" :value="$hymn->title" required /></div>
                </div>
                <x-ff.textarea name="lyrics" :label="__('Words of the hymn')" :value="old('lyrics', $lyrics)" rows="18" required
                    :help="__('Write each verse as its own paragraph, with an empty line between verses. Start the chorus with the word Refrain: (or Chorus:). Numbers like 1. are added for you.')" />
                <div class="small text-ff-muted"><strong>{{ __('Example') }}</strong>
<pre class="bg-light border rounded p-2 mb-0 small">Amazing grace! How sweet the sound
That saved a wretch like me!

'Twas grace that taught my heart to fear,
And grace my fears relieved.

Refrain:
Sing it again, sing it again.</pre></div>
            </div>

            <div class="col-lg-5">
                <h2 class="h6">{{ __('Credits') }}</h2>
                <x-ff.input name="author" :label="__('Words by')" :value="$hymn->author" required :help="__('The author, with years if you know them: John Newton (1725–1807). Write “Unknown” or “Traditional” when it is not known.')" />
                <div class="row">
                    <div class="col-md-8"><x-ff.input name="composer" :label="__('Music by')" :value="$hymn->composer" /></div>
                    <div class="col-md-4"><x-ff.input name="year_written" type="number" :label="__('Year')" :value="$hymn->year_written" /></div>
                </div>
                <div class="row">
                    <div class="col-md-6"><x-ff.input name="translator" :label="__('Translated by')" :value="$hymn->translator" /></div>
                    <div class="col-md-6"><x-ff.input name="arranger" :label="__('Arranged by')" :value="$hymn->arranger" /></div>
                </div>
                <x-ff.select name="copyright_status" :label="__('Copyright')" :options="collect(\App\Models\Hymn::COPYRIGHT)->map(fn ($l) => __($l))->all()" :value="$hymn->copyright_status ?: 'public_domain'" required
                    :help="__('Only add hymns that are in the public domain, that the owner allowed you to use, or that were written for FaithFellows.')" />
                <x-ff.textarea name="credit_note" :label="__('Credit note (optional)')" :value="$hymn->credit_note" rows="3" :help="__('Anything else the source asks you to say, for example the permission you were given.')" />
                <x-ff.input name="source_url" type="url" :label="__('Where the text came from (link)')" :value="$hymn->source_url" placeholder="https://" />

                <h2 class="h6 mt-4">{{ __('Details') }}</h2>
                <div class="row">
                    <div class="col-md-6"><x-ff.input name="tune" :label="__('Tune name')" :value="$hymn->tune" /></div>
                    <div class="col-md-3"><x-ff.input name="meter" :label="__('Metre')" :value="$hymn->meter" placeholder="8.6.8.6" /></div>
                    <div class="col-md-3"><x-ff.input name="key_signature" :label="__('Key')" :value="$hymn->key_signature" placeholder="G" /></div>
                </div>
                <x-ff.input name="themes" :label="__('Themes')" :value="old('themes', collect($hymn->themes)->implode(', '))" :help="__('Separated by commas: grace, worship, Christmas.')" />
                <x-ff.input name="scripture" :label="__('Scripture')" :value="$hymn->scripture" placeholder="John 3:16" />
                <x-ff.input name="audio_url" type="url" :label="__('Audio of the tune (link, optional)')" :value="$hymn->audio_url" placeholder="https://" :help="__('A direct https link to an audio file. Members can play it to learn the tune.')" />
                <x-ff.input name="sheet_music_url" type="url" :label="__('Sheet music (link, optional)')" :value="$hymn->sheet_music_url" placeholder="https://" />
                <x-ff.select name="status" :label="__('Status')" :options="['published' => __('Published'), 'draft' => __('Draft (hidden from members)')]" :value="$hymn->status ?: 'published'" required />
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-ff-primary">{{ $editing ? __('Save hymn') : __('Add hymn') }}</button>
            <a href="{{ route('super-admin.hymnals.show', $hymnal) }}" class="btn btn-ff-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.super-admin>
