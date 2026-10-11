<article @class(['ff-card ff-feed-item', 'border-danger' => $announcement->priority === 'urgent'])>
    <div class="ff-feed-meta">
        <x-ff.church-avatar :church="$announcement->church" size="sm" />
        <div class="min-w-0">
            <a href="{{ route('churches.show', $announcement->church) }}" class="fw-semibold text-ff-black">{{ $announcement->church->name }}</a>
            <div class="small-xs text-ff-muted">{{ $announcement->published_at->diffForHumans() }}</div>
        </div>
        <span @class(['ff-feed-type ff-badge', 'ff-badge-danger' => $announcement->priority === 'urgent', 'ff-badge-primary' => $announcement->priority !== 'urgent'])>
            <i class="bi bi-megaphone"></i> {{ $announcement->priority === 'urgent' ? __('Urgent') : __('Announcement') }}
        </span>
    </div>
    <a href="{{ route('announcements.show', $announcement) }}" class="ff-card-link">
        <div class="fw-bold text-ff-black">{{ $announcement->title }}</div>
        <p class="small text-ff-muted mb-0">{{ Str::limit(strip_tags($announcement->body), 220) }}</p>
    </a>
    @if ($announcement->image_url)
        <img src="{{ $announcement->image_url }}" alt="" class="w-100 rounded-3 ff-cover" loading="lazy">
    @endif
</article>
