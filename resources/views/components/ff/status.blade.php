@props(['status'])
@php
    $map = [
        'active' => 'success', 'approved' => 'success', 'published' => 'success', 'succeeded' => 'success', 'paid' => 'success',
        'completed' => 'success', 'answered' => 'success', 'confirmed' => 'success', 'checked_in' => 'success', 'reached' => 'success',
        'matched' => 'success', 'resolved' => 'success', 'fulfilled' => 'success', 'visible' => 'success', 'attended' => 'success',
        'live' => 'danger', 'pending' => 'warning', 'processing' => 'warning', 'past_due' => 'warning', 'unpaid' => 'warning',
        'waiting' => 'warning', 'unmatched' => 'warning', 'requested' => 'warning', 'scheduled' => 'info', 'open' => 'info',
        'trial' => 'info', 'in_progress' => 'info', 'refunded' => 'info', 'suspended' => 'danger', 'failed' => 'danger',
        'rejected' => 'danger', 'hidden' => 'danger', 'overdue' => 'danger', 'disputed' => 'danger', 'declined' => 'danger',
    ];
    $tone = $map[$status] ?? 'default';
@endphp
<span {{ $attributes->class(['ff-badge', 'ff-badge-'.$tone => $tone !== 'default']) }}>{{ $slot->isNotEmpty() ? $slot : Str::headline($status) }}</span>
