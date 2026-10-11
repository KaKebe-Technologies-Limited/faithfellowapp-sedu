@props(['user' => null, 'name' => null, 'src' => null, 'size' => null])
@php
    $name ??= $user?->name;
    $src ??= $user?->avatar_url;
    $classes = ['ff-avatar', $size ? 'ff-avatar-'.$size : null];
@endphp
@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" loading="lazy" {{ $attributes->class($classes) }}>
@else
    <span {{ $attributes->class($classes) }} aria-hidden="true">{{ ff_initials($name) }}</span>
@endif
