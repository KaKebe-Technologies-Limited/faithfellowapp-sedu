@props(['name', 'label' => null, 'options' => [], 'value' => null, 'help' => null, 'required' => false, 'placeholder' => null])
@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], ['_', '', '_'], $name));
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $current = (string) old($key, $value);
@endphp
<div class="mb-3">
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif</label>
    @endif
    <select name="{{ $name }}" id="{{ $id }}" @required($required) {{ $attributes->except('id')->class(['form-select', 'is-invalid' => $errors->has($key)]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($current === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
