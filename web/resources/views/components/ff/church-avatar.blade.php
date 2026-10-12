@props(['church', 'size' => null])
@php($logo = $church->logo_url ?? null)
@if ($logo)
    <img src="{{ $logo }}" alt="{{ $church->name }}" loading="lazy" {{ $attributes->class(['ff-avatar ff-avatar-square', $size ? 'ff-avatar-'.$size : null]) }}>
@else
    <span {{ $attributes->class(['ff-avatar ff-avatar-square', $size ? 'ff-avatar-'.$size : null]) }} @if (! empty($church->brand_color)) style="background: {{ $church->brand_color }}" @endif aria-hidden="true">{{ ff_initials($church->name) }}</span>
@endif
