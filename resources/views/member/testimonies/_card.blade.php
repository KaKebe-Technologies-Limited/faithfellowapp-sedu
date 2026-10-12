{{-- Approved testimony. Expects $testimony (with church, user). Optional $engagement (bool). --}}
<article class="ff-card d-flex flex-column gap-2 h-100">
    <div class="ff-feed-meta">
        @if ($testimony->is_anonymous)
            <span class="ff-avatar ff-avatar-sm" aria-hidden="true"><i class="bi bi-incognito"></i></span>
        @else
            <x-ff.avatar :user="$testimony->user" size="sm" />
        @endif
        <div class="min-w-0">
            <strong class="d-block text-truncate">{{ $testimony->author_name }}</strong>
            <div class="small-xs text-ff-muted text-truncate">{{ $testimony->church->name }} · {{ ($testimony->reviewed_at ?? $testimony->created_at)->diffForHumans() }}</div>
        </div>
        <span class="ff-feed-type ff-tag"><i class="bi bi-stars"></i> {{ __('Testimony') }}</span>
    </div>
    <h3 class="h5 mb-0 text-ff-black">{{ $testimony->title }}</h3>
    <div class="text-ff-black" style="white-space: pre-line">{{ $testimony->body }}</div>
    @if ($engagement ?? true)
        <div class="mt-auto">@include('partials.engagement-bar', ['model' => $testimony, 'type' => 'testimony'])</div>
    @endif
</article>
