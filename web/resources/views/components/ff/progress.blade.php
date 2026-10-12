@props(['value' => 0, 'size' => null, 'label' => null])
@php($pct = max(0, min(100, (float) $value)))
<div {{ $attributes->class(['ff-progress', $size ? 'ff-progress-'.$size : null]) }} role="progressbar" aria-valuenow="{{ round($pct) }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $label ?? __('Progress') }}">
    <div @class(['ff-progress-bar', 'is-complete' => $pct >= 100]) style="width: {{ $pct }}%"></div>
</div>
