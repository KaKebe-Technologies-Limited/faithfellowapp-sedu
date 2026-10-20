<x-layouts.church-admin :church="$church" :title="$sermon->exists ? __('Edit sermon') : __('New sermon')">
    <x-ff.page-header :title="$sermon->exists ? __('Edit sermon') : __('Upload a sermon')"
        :breadcrumbs="[__('Sermons') => route('church-admin.sermons.index', $church), ($sermon->exists ? $sermon->title : __('New')) => null]" />

    @php($transcript = $sermon->exists ? $sermon->transcripts->firstWhere('language', $sermon->language) : null)
    <form method="POST" enctype="multipart/form-data" action="{{ $sermon->exists ? route('church-admin.sermons.update', [$church, $sermon]) : route('church-admin.sermons.store', $church) }}" class="row g-4" novalidate>
        @csrf
        @if ($sermon->exists) @method('PUT') @endif
        <div class="col-lg-8 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <x-ff.input name="title" :label="__('Title')" :value="$sermon->title" required />
                <div class="row">
                    <div class="col-md-6"><x-ff.input name="preacher" :label="__('Preacher')" :value="$sermon->preacher" /></div>
                    <div class="col-md-6"><x-ff.input name="scripture" :label="__('Scripture')" :value="$sermon->scripture" placeholder="John 3:16-21" /></div>
                    <div class="col-md-4"><x-ff.select name="language" :label="__('Language')" :options="config('faithfellows.languages')" :value="$sermon->language" required /></div>
                    <div class="col-md-4"><x-ff.input name="preached_on" type="date" :label="__('Preached on')" :value="$sermon->preached_on?->format('Y-m-d')" /></div>
                    <div class="col-md-4"><x-ff.input name="duration_minutes" type="number" :label="__('Length (minutes)')" :value="$sermon->duration_seconds ? intdiv($sermon->duration_seconds, 60) : null" min="1" /></div>
                </div>
                <x-ff.textarea name="description" :label="__('Summary / notes')" :value="$sermon->description" editor />
            </div>

            <div class="ff-card">
                <h2 class="h6">{{ __('Media') }}</h2>
                <p class="small text-ff-muted">{{ __('Upload audio (MP3 at 64 kbps keeps it small for members on mobile data) and/or link a video. Audio up to :audio MB, video up to :video MB per file.', ['audio' => ff_audio_upload_mb(auth()->user()), 'video' => config('faithfellows.limits.max_upload_mb')]) }}</p>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label" for="audio">{{ __('Audio file') }}</label>
                        <input type="file" id="audio" name="audio" accept="audio/*" class="form-control @error('audio') is-invalid @enderror">
                        @error('audio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if ($sermon->exists && $sermon->getFirstMedia('audio'))
                            <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_audio" value="1" id="remove_audio"><label class="form-check-label small" for="remove_audio">{{ __('Remove current audio (:name)', ['name' => $sermon->getFirstMedia('audio')->file_name]) }}</label></div>
                        @endif
                    </div>
                    <div class="col-md-6"><x-ff.input name="audio_url" type="url" :label="__('…or audio URL')" :value="$sermon->audio_url" placeholder="https://" /></div>
                    <div class="col-md-6"><x-ff.input name="video_url" type="url" :label="__('Video link (YouTube / Facebook / MP4)')" :value="$sermon->video_url" placeholder="https://youtu.be/…" /></div>
                    <div class="col-md-6">
                        <label class="form-label" for="video">{{ __('…or upload video') }}</label>
                        <input type="file" id="video" name="video" accept="video/mp4,video/webm" class="form-control @error('video') is-invalid @enderror">
                        @error('video')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mt-3">
                        <label class="form-label" for="notes">{{ __('Sermon notes (PDF)') }}</label>
                        <input type="file" id="notes" name="notes" accept="application/pdf" class="form-control @error('notes') is-invalid @enderror">
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mt-3">
                        <label class="form-label" for="thumbnail">{{ __('Thumbnail image') }}</label>
                        <input type="file" id="thumbnail" name="thumbnail" accept="image/*" class="form-control @error('thumbnail') is-invalid @enderror">
                        @error('thumbnail')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="ff-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0">{{ __('Transcript') }}</h2>
                    @if ($sermon->exists)
                        <button type="submit" form="transcribeForm" class="btn btn-sm btn-ff-soft"><i class="bi bi-stars"></i> {{ __('Request AI transcript') }}</button>
                    @endif
                </div>
                @if ($transcript && $transcript->source === 'ai' && $transcript->status !== 'completed')
                    <div class="alert alert-info small py-2"><x-ff.status :status="$transcript->status" /> {{ $transcript->error }}</div>
                @endif
                <x-ff.textarea name="transcript" :value="$transcript?->body" rows="5" :help="__('Optional. Makes the sermon searchable by what was said.')" />
            </div>
        </div>

        <div class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <h2 class="h6">{{ __('Publishing') }}</h2>
                <x-ff.select name="status" :label="__('Status')" :options="['published' => __('Publish'), 'draft' => __('Save as draft')]" :value="$sermon->status === 'draft' ? 'draft' : 'published'" />
                <x-ff.input name="published_at" type="datetime-local" :label="__('Publish at')" :value="$sermon->published_at?->format('Y-m-d\TH:i')" :help="__('Leave empty to publish now, or pick a future time to schedule.')" />
                <x-ff.select name="visibility" :label="__('Who can see it')" :options="['public' => __('Everyone (public)'), 'members' => __('Members only')]" :value="$sermon->visibility" />
                <button class="btn btn-ff-primary w-100">{{ $sermon->exists ? __('Save changes') : __('Save sermon') }}</button>
                <p class="small text-ff-muted mt-2 mb-0">{{ __('Subscribers are notified once when the sermon goes live.') }}</p>
            </div>
            <div class="ff-card">
                <h2 class="h6">{{ __('Organise') }}</h2>
                <x-ff.select name="sermon_series_id" :label="__('Series')" :options="$series" :value="$sermon->sermon_series_id" :placeholder="__('None')" />
                <x-ff.input name="new_series" :label="__('…or start a new series')" />
                @if ($branches->count() > 1)
                    <x-ff.select name="branch_id" :label="__('Branch')" :options="$branches" :value="$sermon->branch_id" :placeholder="__('All branches')" />
                @endif
            </div>
        </div>
    </form>

    @if ($sermon->exists)
        <form method="POST" action="{{ route('church-admin.sermons.transcribe', [$church, $sermon]) }}" id="transcribeForm">@csrf</form>
    @endif
</x-layouts.church-admin>
