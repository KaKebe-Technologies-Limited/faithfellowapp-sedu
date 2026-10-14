{{-- Home-slider photos manager. Expects $church (with media). --}}
@php
    $slides = $church->slides();
    $min = (int) config('faithfellows.limits.min_slides', 4);
    $max = (int) config('faithfellows.limits.max_slides', 8);
@endphp
<section class="ff-card mt-4" style="max-width: 900px" id="slides">
    <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
        <div class="flex-grow-1">
            <h2 class="h5 mb-1">{{ __('Home slider photos') }}</h2>
            <p class="small text-ff-muted mb-0">{{ __('Members see these in the slider at the top of their home screen, taking turns with their other churches. Use bright, wide photos (at least 1600×700) — worship, people, events.') }}</p>
        </div>
        <span @class(['ff-badge', 'ff-badge-success' => $slides->count() >= $min, 'ff-badge-warning' => $slides->count() < $min])>
            {{ $slides->count() }} / {{ $max }} · {{ $slides->count() >= $min ? __('Showing on home screens') : __(':n more needed', ['n' => $min - $slides->count()]) }}
        </span>
    </div>

    @if ($slides->isNotEmpty())
        <div class="d-grid gap-3 my-3">
            @foreach ($slides as $i => $slide)
                <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center p-2 rounded-4 border">
                    <div class="ff-slide-thumb flex-shrink-0" style="width: 220px">
                        <img src="{{ $slide->getUrl('slide-sm') }}" alt="" loading="lazy">
                        <span class="ff-slide-thumb-label">#{{ $i + 1 }}</span>
                    </div>
                    <form method="POST" action="{{ route('church-admin.slides.update', [$church, $slide->id]) }}" class="flex-grow-1 d-grid gap-2">@csrf @method('PUT')
                        <input name="title" class="form-control form-control-sm fw-semibold" maxlength="80" value="{{ $slide->getCustomProperty('title') }}" placeholder="{{ __('Caption title (optional) — e.g. Welcome home') }}" aria-label="{{ __('Caption title') }}">
                        <div class="d-flex gap-2">
                            <input name="subtitle" class="form-control form-control-sm" maxlength="140" value="{{ $slide->getCustomProperty('subtitle') }}" placeholder="{{ __('Subtitle — e.g. Sundays 10:00 AM') }}" aria-label="{{ __('Caption subtitle') }}">
                            <button class="btn btn-sm btn-ff-soft text-nowrap">{{ __('Save') }}</button>
                        </div>
                    </form>
                    <div class="d-flex gap-1 align-self-end align-self-md-center">
                        <form method="POST" action="{{ route('church-admin.slides.move', [$church, $slide->id]) }}">@csrf
                            <button name="direction" value="up" class="btn btn-sm btn-ff-ghost" @disabled($loop->first) aria-label="{{ __('Move up') }}"><i class="bi bi-arrow-up"></i></button>
                            <button name="direction" value="down" class="btn btn-sm btn-ff-ghost" @disabled($loop->last) aria-label="{{ __('Move down') }}"><i class="bi bi-arrow-down"></i></button>
                        </form>
                        <form method="POST" action="{{ route('church-admin.slides.destroy', [$church, $slide->id]) }}" data-ff-confirm="{{ __('Remove this photo from the slider?') }}">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Remove') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($slides->count() < $max)
        <form method="POST" action="{{ route('church-admin.slides.store', $church) }}" enctype="multipart/form-data" class="p-3 rounded-4 bg-ff-mist mt-2">@csrf
            <label class="form-label fw-semibold" for="slides-upload"><i class="bi bi-images"></i> {{ __('Add photos') }}</label>
            <input type="file" id="slides-upload" name="slides[]" accept="image/png,image/jpeg,image/webp" multiple required class="form-control @error('slides') is-invalid @enderror @error('slides.*') is-invalid @enderror">
            @error('slides')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @error('slides.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="row g-2 mt-1">
                <div class="col-md-5"><input name="title" class="form-control form-control-sm" maxlength="80" placeholder="{{ __('Caption title (optional)') }}" aria-label="{{ __('Caption title') }}"></div>
                <div class="col-md-5"><input name="subtitle" class="form-control form-control-sm" maxlength="140" placeholder="{{ __('Subtitle (optional)') }}" aria-label="{{ __('Caption subtitle') }}"></div>
                <div class="col-md-2"><button class="btn btn-ff-primary btn-sm w-100">{{ __('Upload') }}</button></div>
            </div>
            <div class="form-text">{{ __('JPG, PNG or WebP, up to 6 MB each. Photos are cropped to a wide 16:7 frame.') }}</div>
        </form>
    @endif
</section>
