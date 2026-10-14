{{-- Preview of a reported item. Expects $report (with reportable). --}}
@php
    $item = $report->reportable;
    $kind = array_search($report->reportable_type, \App\Models\ContentReport::TYPES, true) ?: class_basename($report->reportable_type);
    $text = $item?->body ?? $item?->title ?? null;
    $hidden = $item && (in_array($item->status ?? null, ['hidden', 'rejected'], true) || ($item->is_hidden ?? false) || method_exists($item, 'trashed') && $item->trashed());
@endphp
<span class="ff-tag">{{ Str::headline($kind) }}</span>
@if ($hidden)<span class="ff-badge ff-badge-danger">{{ __('Hidden') }}</span>@endif
<div class="small mt-1" style="white-space: pre-line">{{ $text ? Str::limit(strip_tags($text), 220) : __('(deleted)') }}</div>
@if ($item && isset($item->user_id))
    <div class="small-xs text-ff-muted">{{ __('By :name', ['name' => \App\Models\User::find($item->user_id)?->name ?? '—']) }}</div>
@endif
