@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'help' => null, 'required' => false, 'prepend' => null, 'append' => null])
@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], ['_', '', '_'], $name));
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $current = $type === 'password' ? null : old($key, $value);
@endphp
<div class="mb-3">
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif</label>
    @endif
    @if ($prepend || $append)<div class="input-group has-validation">@endif
        @if ($prepend)<span class="input-group-text">{{ $prepend }}</span>@endif
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $current }}" @required($required)
            {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errors->has($key)]) }}
            @if ($help) aria-describedby="{{ $id }}-help" @endif>
        @if ($append)<span class="input-group-text">{{ $append }}</span>@endif
        @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($prepend || $append)</div>@endif
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
</div>
