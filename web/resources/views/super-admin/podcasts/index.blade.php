<x-layouts.super-admin :title="__('Podcasts')">
    <x-ff.page-header :title="__('Podcasts')" :subtitle="__('Faith-based podcasts made by members. A podcast shows in the app only after you approve it.')" />

    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
        <nav class="ff-pills" aria-label="{{ __('Filter') }}">
            @foreach (['pending' => __('To review'), 'approved' => __('Live in the app'), 'rejected' => __('Not approved'), 'hidden' => __('Hidden'), 'all' => __('All')] as $key => $label)
                <a href="{{ route('super-admin.podcasts.index', ['status' => $key]) }}" @class(['ff-pill', 'active' => $status === $key])>{{ $label }} @if ($key !== 'all')<span class="opacity-75">{{ $counts[$key] ?? 0 }}</span>@endif</a>
            @endforeach
        </nav>
        <form method="GET" class="d-flex gap-2" role="search">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Search podcasts…') }}" aria-label="{{ __('Search podcasts') }}">
        </form>
    </div>

    <div class="ff-table-wrap mb-4">
        <div class="table-responsive">
            <table class="table ff-table align-middle">
                <thead><tr><th>{{ __('Podcast') }}</th><th>{{ __('Podcaster') }}</th><th>{{ __('Episodes') }}</th><th>{{ __('Status') }}</th><th>{{ __('Sent') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($podcasts as $podcast)
                        <tr>
                            <td class="d-flex align-items-center gap-2">
                                <img src="{{ $podcast->coverUrl() }}" alt="" class="rounded-2 ff-cover" style="width:48px;height:48px;object-fit:cover">
                                <span><span class="fw-semibold text-ff-black d-block">{{ $podcast->name }}</span><span class="small-xs text-ff-muted">{{ $podcast->categoryLabel() }}</span></span>
                            </td>
                            <td class="small">{{ $podcast->host_name }}<div class="small-xs text-ff-muted">{{ $podcast->owner?->name }}</div></td>
                            <td class="small">{{ $podcast->episodes_count }}</td>
                            <td><x-ff.status :status="$podcast->status" /></td>
                            <td class="small text-nowrap">{{ $podcast->created_at?->diffForHumans() }}</td>
                            <td class="text-end"><a href="{{ route('super-admin.podcasts.show', $podcast) }}" class="btn btn-sm btn-ff-soft">{{ $podcast->status === 'pending' ? __('Review') : __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ff.empty-state icon="bi-broadcast-pin" :title="__('Nothing here')" :message="$status === 'pending' ? __('No podcasts are waiting for review.') : __('No podcasts match.')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($podcasts->hasPages())<div class="ff-table-footer">{{ $podcasts->links() }}</div>@endif
    </div>

    <form method="POST" action="{{ route('super-admin.podcasts.hosts') }}" class="ff-card">@csrf @method('PUT')
        <h2 class="h6">{{ __('Where podcasters keep their audio') }}</h2>
        <p class="small text-ff-muted">{{ __('Podcasters upload their audio to these sites and paste the link into FaithFellows. Write the sites separated by commas, like: podbean.com, buzzsprout.com. Leave it empty to allow any https link.') }}</p>
        <textarea name="hosts" rows="2" class="form-control mb-3" aria-label="{{ __('Allowed host sites') }}">{{ $hosts }}</textarea>
        <button class="btn btn-ff-primary">{{ __('Save host sites') }}</button>
    </form>
</x-layouts.super-admin>
