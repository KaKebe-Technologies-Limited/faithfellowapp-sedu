{{-- FaithFellows logo: rounded-square mark with cross + swoosh, and the "FaithFellows" wordmark. --}}
@props(['variant' => 'full', 'size' => 34, 'white' => false, 'href' => null])
@php
    $tag = $href ? 'a' : 'span';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['ff-logo', 'is-white' => $white]) }} aria-label="FaithFellows">
    @if ($variant !== 'word')
        <x-logo-mark :size="$size" />
    @endif
    @if ($variant !== 'icon')
        <span class="ff-logo-word" style="font-size: {{ round($size * 0.62, 2) }}px">Faith<span style="font-weight:700">Fellows</span></span>
    @endif
</{{ $tag }}>
