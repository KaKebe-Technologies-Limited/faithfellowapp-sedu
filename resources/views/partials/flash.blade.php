{{-- Flash messages are rendered as toasts by faithfellows.js (no-JS fallback: visible alerts). --}}
<div class="ff-toast-container" aria-live="polite">
    @foreach (['success' => 'success', 'status' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $key => $type)
        @if (session()->has($key) && is_string(session($key)) && ! in_array(session($key), ['verification-link-sent', 'profile-updated', 'password-updated'], true))
            <span data-ff-toast="{{ session($key) }}" data-ff-toast-type="{{ $type }}"></span>
            <noscript><div class="alert alert-{{ $type === 'error' ? 'danger' : $type }}">{{ session($key) }}</div></noscript>
        @endif
    @endforeach
</div>
