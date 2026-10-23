<x-layouts.super-admin :title="__('App media')">
    <x-ff.page-header :title="__('App media')" :subtitle="__('The pictures of the app itself: what shows when it opens, behind the welcome screen, and on the Home slider. The opening photos show only once you add some; the welcome screen and the Home banner keep the photos built into the app until you add yours.')">
        <a href="{{ route('super-admin.settings') }}#opening_flash_seconds" class="btn btn-ff-ghost"><i class="bi bi-stopwatch"></i> {{ __('How long the opening photos stay') }}</a>
    </x-ff.page-header>

    @unless ($ready)
        <div class="alert alert-warning"><i class="bi bi-database-exclamation"></i> {{ __('The database update for app media has not been applied yet. Open Database updates and press Apply now, then come back.') }}
            <a href="{{ route('super-admin.updates.index') }}" class="alert-link">{{ __('Database updates') }}</a></div>
    @else
        <div class="d-grid gap-4">
            @foreach ($placements as $key => $placement)
                @php $list = $photos->get($key, collect()); @endphp
                <x-ff.card :title="__($placement['label'])" :subtitle="__($placement['where'])" id="media-{{ $key }}">
                    <p class="small text-ff-muted"><i class="bi bi-lightbulb text-ff-primary"></i> {{ __($placement['tip']) }}</p>

                    @if ($list->isEmpty())
                        <div class="ff-card ff-card-gray ff-card-sm small mb-3"><i class="bi bi-box"></i> {{ __($placement['empty']) }}</div>
                    @else
                        <div class="row g-3 mb-3">
                            @foreach ($list as $photo)
                                <div class="col-sm-6 col-lg-4 col-xl-3">
                                    <form method="POST" action="{{ route('super-admin.app-media.update', $photo) }}" class="ff-card ff-card-sm h-100 d-flex flex-column gap-2 {{ $photo->is_active ? '' : 'opacity-75' }}">@csrf @method('PUT')
                                        @if ($photo->url())
                                            <img src="{{ $photo->url() }}" alt="" class="rounded-3 w-100" style="aspect-ratio: {{ $key === 'banner' ? '1000 / 640' : '480 / 740' }}; object-fit: cover; background: #eadde3" loading="lazy">
                                        @endif
                                        @if ($key === 'banner')
                                            <input name="title" value="{{ $photo->title }}" maxlength="80" class="form-control form-control-sm" placeholder="{{ __('Title (optional)') }}" aria-label="{{ __('Title') }}">
                                            <input name="subtitle" value="{{ $photo->subtitle }}" maxlength="160" class="form-control form-control-sm" placeholder="{{ __('Sentence under it (optional)') }}" aria-label="{{ __('Sentence') }}">
                                        @endif
                                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="on-{{ $photo->id }}" @checked($photo->is_active)><label class="form-check-label small" for="on-{{ $photo->id }}">{{ __('Show it') }}</label></div>
                                        <div class="d-flex gap-1 mt-auto">
                                            <button class="btn btn-sm btn-ff-soft">{{ __('Save') }}</button>
                                            <button type="submit" formaction="{{ route('super-admin.app-media.move', $photo) }}" name="direction" value="up" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Move earlier') }}" @disabled($loop->first)><i class="bi bi-arrow-left"></i></button>
                                            <button type="submit" formaction="{{ route('super-admin.app-media.move', $photo) }}" name="direction" value="down" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Move later') }}" @disabled($loop->last)><i class="bi bi-arrow-right"></i></button>
                                            <button type="submit" formaction="{{ route('super-admin.app-media.destroy', $photo) }}" formmethod="POST" name="_method" value="DELETE" class="btn btn-sm btn-ff-ghost text-danger ms-auto" data-ff-confirm="{{ __('Remove this picture?') }}" aria-label="{{ __('Remove') }}"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($list->count() < $placement['max'])
                        <form method="POST" action="{{ route('super-admin.app-media.store') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-end">@csrf
                            <input type="hidden" name="placement" value="{{ $key }}">
                            <div class="flex-grow-1">
                                <label class="form-label small fw-semibold" for="photos-{{ $key }}">{{ __('Add pictures (up to :n more; JPG, PNG or WebP, 8 MB each)', ['n' => $placement['max'] - $list->count()]) }}</label>
                                <input type="file" id="photos-{{ $key }}" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required class="form-control @error('photos') is-invalid @enderror">
                                @if (old('placement') === $key)@error('photos')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @error('photos.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <button class="btn btn-ff-primary"><i class="bi bi-upload"></i> {{ __('Upload') }}</button>
                        </form>
                    @else
                        <div class="small text-ff-muted">{{ __('This place has the most pictures it can show. Remove one to add another.') }}</div>
                    @endif
                </x-ff.card>
            @endforeach
        </div>

        <p class="small text-ff-muted mt-4"><i class="bi bi-phone"></i> {{ __('Phones pick up changes the next time the app is opened. Make sure you have the right to use every picture, and the permission of the people in them.') }}</p>
    @endunless
</x-layouts.super-admin>
