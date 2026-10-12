@props(['name', 'label' => null, 'value' => null, 'help' => null, 'required' => false, 'rows' => 4, 'editor' => false])
@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], ['_', '', '_'], $name));
    $key = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div class="mb-3">
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" @required($required && ! $editor) @if ($editor) data-ff-editor @endif
        {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errors->has($key)]) }}>{{ old($key, $value) }}</textarea>
    @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
