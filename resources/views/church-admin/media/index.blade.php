<x-layouts.church-admin :church="$church" :title="__('Media library')">
    <x-ff.page-header :title="__('Media library')" :subtitle="__('Every file your church has uploaded — sermons, photos, documents.')" />

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="ff-card h-100">
                <h2 class="h6">{{ __('Storage') }}</h2>
                <div class="d-flex justify-content-between small mb-1"><span>{{ $usedMb }} MB {{ __('used') }}</span><span class="text-ff-muted">{{ number_format($limitMb) }} MB</span></div>
                <x-ff.progress :value="$percent" :label="__('Storage used')" />
                <p class="small text-ff-muted mt-2 mb-0">{{ $percent >= 90 ? __('You are almost out of space. Remove old files or upgrade your plan.') : __('Your plan includes :gb GB of media storage.', ['gb' => round($limitMb / 1024, 1)]) }}</p>
            </div>
        </div>
        <div class="col-lg-7">
            <form method="POST" action="{{ route('church-admin.media.store', $church) }}" enctype="multipart/form-data" class="ff-card h-100" novalidate>@csrf
                <h2 class="h6">{{ __('Upload files') }}</h2>
                <input type="file" name="files[]" multiple class="form-control mb-2 @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror" aria-label="{{ __('Files') }}">
                @error('files')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @error('files.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small text-ff-muted">{{ __('Images, audio, video or PDF · up to 20 files') }}</span>
                    <button class="btn btn-ff-primary btn-sm"><i class="bi bi-upload"></i> {{ __('Upload') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
        <div class="ff-pills">
            @foreach (['' => __('All'), 'image' => __('Images'), 'audio' => __('Audio'), 'video' => __('Video'), 'document' => __('Documents')] as $key => $label)
                <a href="{{ route('church-admin.media.index', [$church, 'type' => $key ?: null]) }}" @class(['ff-pill', 'active' => request('type', '') === $key])>{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET"><input type="hidden" name="type" value="{{ request('type') }}"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search files…') }}" aria-label="{{ __('Search files') }}"></form>
    </div>

    @if ($media->isEmpty())
        <x-ff.empty-state icon="bi-images" :title="__('No files yet')" />
    @else
        <div class="row g-3">
            @foreach ($media as $item)
                @php($kind = Str::before($item->mime_type, '/'))
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="ff-card ff-card-flush h-100 d-flex flex-column">
                        @if ($kind === 'image')
                            <img src="{{ $item->hasGeneratedConversion('thumb') ? $item->getUrl('thumb') : $item->getUrl() }}" alt="" class="w-100" style="aspect-ratio:1;object-fit:cover" loading="lazy">
                        @else
                            <div class="d-flex align-items-center justify-content-center bg-ff-mist text-ff-primary" style="aspect-ratio:1;font-size:2.5rem">
                                <i class="bi {{ match ($kind) { 'audio' => 'bi-file-earmark-music', 'video' => 'bi-file-earmark-play', default => 'bi-file-earmark-text' } }}"></i>
                            </div>
                        @endif
                        <div class="p-2 small">
                            <div class="text-truncate fw-semibold text-ff-black" title="{{ $item->file_name }}">{{ $item->name }}</div>
                            <div class="small-xs text-ff-muted">{{ $item->human_readable_size }} · {{ class_basename($item->model_type) }}</div>
                            <div class="d-flex gap-1 mt-1">
                                <button type="button" class="btn btn-sm btn-ff-ghost btn-icon" data-ff-copy="{{ $item->getUrl() }}" aria-label="{{ __('Copy link') }}"><i class="bi bi-link-45deg"></i></button>
                                <a href="{{ $item->getUrl() }}" target="_blank" class="btn btn-sm btn-ff-ghost btn-icon" aria-label="{{ __('Open') }}"><i class="bi bi-box-arrow-up-right"></i></a>
                                <form method="POST" action="{{ route('church-admin.media.destroy', [$church, $item->id]) }}" class="ms-auto" data-ff-confirm="{{ __('Delete this file? Anything using it will lose it.') }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ff-ghost btn-icon text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $media->links() }}</div>
    @endif
</x-layouts.church-admin>
