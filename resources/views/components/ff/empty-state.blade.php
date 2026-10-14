@props(['icon' => 'bi-inbox', 'title', 'message' => null])
<div {{ $attributes->class('ff-empty-state') }}>
    <div class="ff-empty-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
    <h3>{{ $title }}</h3>
    @if ($message)
        <p>{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
