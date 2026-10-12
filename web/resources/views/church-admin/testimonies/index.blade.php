<x-layouts.church-admin :church="$church" :title="__('Testimonies')">
    <x-ff.page-header :title="__('Testimonies')" :subtitle="__('Review testimonies before they appear in the feed and on your church page.')" />

    <nav class="ff-tabs mb-4" aria-label="{{ __('Status') }}">
        @foreach (['pending' => __('Awaiting review'), 'approved' => __('Published'), 'rejected' => __('Rejected')] as $value => $label)
            <a href="{{ route('church-admin.testimonies.index', [$church, 'status' => $value]) }}" @class(['ff-tab', 'active' => $status === $value])>
                {{ $label }} <span class="ff-badge ms-1">{{ $counts[$value] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <div class="d-grid gap-3" style="max-width: 900px">
        @forelse ($testimonies as $t)
            <article class="ff-card">
                <div class="ff-feed-meta mb-2">
                    <x-ff.avatar :user="$t->user" size="sm" />
                    <div class="min-w-0">
                        <strong>{{ $t->user?->name ?? __('Deleted user') }}</strong>
                        @if ($t->is_anonymous)<span class="small-xs text-ff-muted"><i class="bi bi-incognito"></i> {{ __('wants to stay anonymous') }}</span>@endif
                        <div class="small-xs text-ff-muted">{{ $t->created_at->format('j M Y, H:i') }}</div>
                    </div>
                    <span class="ff-feed-type"><x-ff.status :status="$t->status" /></span>
                </div>
                <h2 class="h5">{{ $t->title }}</h2>
                <div style="white-space: pre-line">{{ $t->body }}</div>
                @if ($t->prayerRequest)
                    <div class="small text-ff-muted mt-2"><i class="bi bi-link-45deg"></i> {{ __('Answer to prayer:') }} “{{ Str::limit($t->prayerRequest->body, 100) }}”</div>
                @endif
                <div class="d-flex gap-2 mt-3">
                    <form method="POST" action="{{ route('church-admin.testimonies.update', [$church, $t]) }}" class="d-flex gap-2">@csrf @method('PUT')
                        @if ($t->status !== 'approved')<button name="status" value="approved" class="btn btn-sm btn-ff-primary"><i class="bi bi-check-lg"></i> {{ __('Approve & publish') }}</button>@endif
                        @if ($t->status !== 'rejected')<button name="status" value="rejected" class="btn btn-sm btn-ff-soft">{{ $t->status === 'approved' ? __('Unpublish') : __('Reject') }}</button>@endif
                    </form>
                    <form method="POST" action="{{ route('church-admin.testimonies.destroy', [$church, $t]) }}" class="ms-auto" data-ff-confirm="{{ __('Delete this testimony?') }}">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </article>
        @empty
            <x-ff.empty-state icon="bi-stars" :title="$status === 'pending' ? __('Nothing to review') : __('No testimonies here')" />
        @endforelse
    </div>
    <div class="mt-4">{{ $testimonies->links() }}</div>
</x-layouts.church-admin>
