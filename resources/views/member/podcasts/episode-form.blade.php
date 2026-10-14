@php($editing = $episode->exists)
<x-layouts.member :title="$editing ? __('Edit episode') : __('New episode')">
    <x-ff.page-header :title="$editing ? __('Edit episode') : __('Add an episode')" :subtitle="$podcast->name"
        :breadcrumbs="[__('My podcasts') => route('podcasts.index'), $podcast->name => route('podcasts.show', $podcast), ($editing ? $episode->title : __('New episode')) => null]" />

    <form method="POST" class="ff-card" novalidate
        action="{{ $editing ? route('podcasts.episodes.update', [$podcast, $episode]) : route('podcasts.episodes.store', $podcast) }}">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-ff.input name="title" :label="__('Episode title')" :value="$episode->title" required />
                <x-ff.textarea name="summary" :label="__('About this episode (optional)')" :value="$episode->summary" rows="4" />
                <x-ff.input name="audio_url" type="url" :label="__('Link to the audio')" :value="$episode->audio_url" required placeholder="https://…/episode-1.mp3"
                    :help="$hosts ? __('Host the audio on: :sites. Paste the direct link to the file; it should open or download the audio, not a web page.', ['sites' => implode(', ', $hosts)]) : __('Paste the direct https link to the audio file. It should open or download the audio, not a web page.')" />
                <div class="row">
                    <div class="col-md-4"><x-ff.input name="duration" :label="__('Length (optional)')" :value="old('duration', $episode->durationLabel())" placeholder="24:10" :help="__('Minutes and seconds.')" /></div>
                    <div class="col-md-4"><x-ff.input name="season" type="number" :label="__('Season (optional)')" :value="$episode->season" /></div>
                    <div class="col-md-4"><x-ff.input name="number" type="number" :label="__('Episode number (optional)')" :value="$episode->number" /></div>
                </div>
            </div>
            <div class="col-lg-4">
                <x-ff.input name="published_at" type="datetime-local" :label="__('Publish on')" :value="optional($episode->published_at)->format('Y-m-d\TH:i')" :help="__('Leave empty to publish now. A later date schedules it.')" />
                <x-ff.checkbox name="draft" :label="__('Keep as a draft')" :checked="$episode->status === 'draft'" switch :help="__('Drafts are not shown in the app.')" />
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-ff-primary">{{ $editing ? __('Save episode') : __('Add episode') }}</button>
            <a href="{{ route('podcasts.show', $podcast) }}" class="btn btn-ff-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.member>
