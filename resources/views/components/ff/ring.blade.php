{{-- Campaign progress ring (SVG). --}}
@props(['value' => 0, 'size' => 140, 'stroke' => 12, 'label' => null, 'sub' => null])
@php
    $pct = max(0, min(100, (float) $value));
    $r = ($size - $stroke) / 2;
    $c = 2 * M_PI * $r;
@endphp
<div {{ $attributes->class('ff-ring') }} data-value="{{ $pct }}" style="width: {{ $size }}px; height: {{ $size }}px" role="img" aria-label="{{ round($pct) }}% {{ __('funded') }}">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}">
        <circle class="ff-ring-track" cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none" stroke-width="{{ $stroke }}" />
        <circle class="ff-ring-value" cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none" stroke-width="{{ $stroke }}"
                stroke-dasharray="{{ $c }}" stroke-dashoffset="{{ $c * (1 - $pct / 100) }}" />
    </svg>
    <div class="ff-ring-label">
        <strong>{{ $label ?? round($pct).'%' }}</strong>
        <span>{{ $sub ?? __('funded') }}</span>
    </div>
</div>
