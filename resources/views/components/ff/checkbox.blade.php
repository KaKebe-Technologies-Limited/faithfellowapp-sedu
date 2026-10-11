@props(['name', 'label', 'checked' => false, 'value' => 1, 'switch' => false, 'help' => null])
@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], ['_', '', '_'], $name).'_'.Str::slug((string) $value));
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $isChecked = old($key) !== null ? (bool) old($key) : (bool) $checked;
@endphp
<div @class(['form-check mb-2', 'form-switch' => $switch])>
    @unless (str_ends_with($name, '[]'))<input type="hidden" name="{{ $name }}" value="0">@endunless
    <input class="form-check-input @error($key) is-invalid @enderror" type="checkbox" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" @checked($isChecked) {{ $attributes->except('id') }} @if ($switch) role="switch" @endif>
    <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
    @if ($help)<div class="form-text mt-0">{{ $help }}</div>@endif
    @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
