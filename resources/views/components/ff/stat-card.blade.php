@props(['label', 'value', 'icon' => 'bi-graph-up', 'trend' => null, 'trendLabel' => null, 'primary' => false, 'href' => null])
<div {{ $attributes->class(['ff-stat-card position-relative', 'is-primary' => $primary]) }}>
    <span class="ff-stat-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
    <span class="ff-stat-label">{{ $label }}</span>
    <span class="ff-stat-value">{{ $value }}</span>
    @if ($trend !== null)
        <span @class(['ff-stat-trend', 'up' => $trend >= 0, 'down' => $trend < 0])>
            <i class="bi {{ $trend >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
            {{ abs($trend) }}% <span class="text-ff-muted fw-normal">{{ $trendLabel ?? __('vs last period') }}</span>
        </span>
    @elseif ($slot->isNotEmpty())
        <span class="small text-ff-muted">{{ $slot }}</span>
    @endif
    @if ($href)
        <a href="{{ $href }}" class="stretched-link" aria-label="{{ $label }}"></a>
    @endif
</div>
