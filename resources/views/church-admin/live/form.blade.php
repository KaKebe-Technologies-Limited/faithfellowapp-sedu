<x-layouts.church-admin :church="$church" :title="$stream->exists ? __('Edit stream') : __('Schedule a service')">
    <x-ff.page-header :title="$stream->exists ? __('Edit stream') : __('Schedule a live service')"
        :breadcrumbs="[__('Live streams') => route('church-admin.live.index', $church), ($stream->exists ? $stream->title : __('New')) => null]" />

    <form method="POST" action="{{ $stream->exists ? route('church-admin.live.update', [$church, $stream]) : route('church-admin.live.store', $church) }}" class="ff-card" style="max-width: 760px"
          x-data="{ source: '{{ old('source_type', $stream->source_type) }}' }" novalidate>
        @csrf
        @if ($stream->exists) @method('PUT') @endif
        <x-ff.input name="title" :label="__('Title')" :value="$stream->title" required />
        <x-ff.textarea name="description" :label="__('Description')" :value="$stream->description" rows="2" />
        <x-ff.input name="scheduled_at" type="datetime-local" :label="__('Starts at')" :value="$stream->scheduled_at?->format('Y-m-d\TH:i')" required />

        <label class="form-label">{{ __('How will you stream?') }}</label>
        <div class="d-grid gap-2 mb-3">
            @foreach (['rtmp' => [__('FaithFellows streaming (OBS / Larix / vMix)'), __('We give you a stream key. Supports audio-only mode for low data.')], 'youtube' => [__('YouTube Live'), __('Paste your YouTube live link; we embed it for your members.')], 'facebook' => [__('Facebook Live'), __('Paste the Facebook video link.')], 'hls' => [__('Other (HLS link)'), __('Any .m3u8 stream URL.')]] as $value => [$label, $hint])
                <label class="ff-method-option">
                    <input type="radio" class="form-check-input mt-0" name="source_type" value="{{ $value }}" x-model="source">
                    <span><strong class="text-ff-black d-block">{{ $label }}</strong><span class="small text-ff-muted">{{ $hint }}</span></span>
                </label>
            @endforeach
        </div>
        <div x-show="source !== 'rtmp'" x-cloak><x-ff.input name="external_url" type="url" :label="__('Stream link')" :value="$stream->external_url" placeholder="https://" /></div>

        <x-ff.checkbox name="chat_enabled" :label="__('Enable moderated live chat')" :checked="$stream->chat_enabled" switch />
        <x-ff.checkbox name="audio_only_available" :label="__('Offer audio-only mode')" :checked="$stream->audio_only_available ?? true" switch />
        <button class="btn btn-ff-primary px-4 mt-3">{{ __('Save') }}</button>
    </form>
</x-layouts.church-admin>
