<a href="{{ route('live.show', $live) }}" class="ff-card bg-ff-gradient border-0 ff-card-link d-flex align-items-center gap-3 text-white">
    <x-ff.church-avatar :church="$live->church" size="lg" class="ff-avatar-ring" />
    <div class="flex-grow-1 min-w-0">
        <span class="ff-badge-live mb-1">{{ __('Live now') }}</span>
        <div class="fw-bold fs-5 text-truncate">{{ $live->church->name }}</div>
        <div class="small" style="opacity:.9">{{ $live->title }}</div>
    </div>
    <span class="btn btn-ff-white">{{ __('Join') }}</span>
</a>
