@props(['eyebrow' => null, 'title', 'lead' => null, 'align' => 'center'])
<div {{ $attributes->class(['ff-section-title' => $align === 'center', 'mb-5' => $align !== 'center']) }}>
    @if ($eyebrow)<span class="ff-eyebrow">{{ $eyebrow }}</span>@endif
    <h2 @class(['mt-2' => $align !== 'center'])>{{ $title }}</h2>
    @if ($lead)<p class="ff-lead mb-0">{{ $lead }}</p>@endif
</div>
