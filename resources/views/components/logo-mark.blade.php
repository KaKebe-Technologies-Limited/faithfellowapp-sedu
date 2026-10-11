@props(['size' => 34, 'filled' => false])
{{-- Geometry traced from the brand guide (500×500 artboard normalised to 100×100). --}}
<svg {{ $attributes->class('ff-logo-mark') }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 100 100" fill="none" aria-hidden="true" focusable="false">
    @if ($filled)
        <rect width="100" height="100" rx="30" fill="currentColor"/>
        <g stroke="#fff" stroke-width="10.2">
            <path d="M22.3 51.6H55.6M50.5 46.5V78.5"/>
            <path d="M49.7 21.7A29.9 29.9 0 0 0 79.6 51.6"/>
        </g>
    @else
        <rect x="5.1" y="5.1" width="89.8" height="89.8" rx="30.4" stroke="currentColor" stroke-width="10.2"/>
        <g stroke="currentColor" stroke-width="10.2">
            <path d="M22.3 51.6H55.6M50.5 46.5V78.5"/>
            <path d="M49.7 21.7A29.9 29.9 0 0 0 79.6 51.6"/>
        </g>
    @endif
</svg>
