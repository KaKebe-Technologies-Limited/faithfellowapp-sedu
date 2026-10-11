@props(['url', 'title' => ''])
@php
    $t = urlencode($title);
    $u = urlencode($url);
@endphp
<div {{ $attributes->class('d-flex flex-wrap gap-2') }}>
    <a class="btn btn-sm btn-ff-soft" href="https://wa.me/?text={{ $t }}%20{{ $u }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
    <a class="btn btn-sm btn-ff-soft" href="https://www.facebook.com/sharer/sharer.php?u={{ $u }}" target="_blank" rel="noopener"><i class="bi bi-facebook"></i> Facebook</a>
    <a class="btn btn-sm btn-ff-soft" href="https://x.com/intent/tweet?text={{ $t }}&amp;url={{ $u }}" target="_blank" rel="noopener"><i class="bi bi-twitter-x"></i> X</a>
    <button type="button" class="btn btn-sm btn-ff-soft" data-ff-copy="{{ $url }}"><i class="bi bi-link-45deg"></i> {{ __('Copy link') }}</button>
    <button type="button" class="btn btn-sm btn-ff-soft d-none" data-ff-share="{{ $url }}" data-ff-share-title="{{ $title }}" x-data x-init="if (navigator.share) $el.classList.remove('d-none')"><i class="bi bi-share"></i> {{ __('Share') }}</button>
</div>
