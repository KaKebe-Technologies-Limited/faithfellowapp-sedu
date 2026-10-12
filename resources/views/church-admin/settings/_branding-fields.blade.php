<div class="row g-4">
    <div class="col-md-5">
        <label class="form-label">{{ __('Logo') }}</label>
        <div class="d-flex align-items-center gap-3 mb-2">
            <x-ff.church-avatar :church="$church" size="xl" />
            <div class="flex-grow-1">
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm @error('logo') is-invalid @enderror" aria-label="{{ __('Logo') }}">
                @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('Square, at least 400×400px, max 2 MB. Other shapes are centre-cropped to a square.') }}</div>
                @if ($church->logo_url)
                    <div class="form-check mt-1"><input type="checkbox" class="form-check-input" name="remove_logo" value="1" id="remove_logo"><label class="form-check-label small" for="remove_logo">{{ __('Remove logo') }}</label></div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <label class="form-label">{{ __('Cover photo') }}</label>
        <div class="rounded-4 mb-2" style="height: 110px; background: {{ $church->cover_url ? "url('".e($church->cover_url)."') center/cover" : 'linear-gradient(135deg, '.e($church->brand_color).', #F27EA8)' }}"></div>
        <input type="file" name="cover" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm @error('cover') is-invalid @enderror" aria-label="{{ __('Cover photo') }}">
        @error('cover')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">{{ __('Wide image, at least 1600×600px, max 5 MB.') }}</div>
        @if ($church->cover_url)
            <div class="form-check mt-1"><input type="checkbox" class="form-check-input" name="remove_cover" value="1" id="remove_cover"><label class="form-check-label small" for="remove_cover">{{ __('Remove cover') }}</label></div>
        @endif
    </div>
    <div class="col-md-5">
        <label for="brand_color" class="form-label">{{ __('Accent colour') }}</label>
        <div class="d-flex gap-2 align-items-center">
            <input type="color" id="brand_color" name="brand_color" value="{{ old('brand_color', $church->brand_color ?? '#BC2649') }}" class="form-control form-control-color">
            <span class="small text-ff-muted">{{ __('Used on your website hero and buttons.') }}</span>
        </div>
        @error('brand_color')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
</div>
