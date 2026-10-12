<x-layouts.super-admin :title="__('Blog')">
    <x-ff.page-header :title="__('Blog')" :subtitle="__('Articles on the public FaithFellows blog.')">
        <a href="{{ route('blog.index') }}" class="btn btn-ff-ghost" target="_blank"><i class="bi bi-box-arrow-up-right"></i> {{ __('View blog') }}</a>
        <a href="{{ route('super-admin.blog.create') }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('New post') }}</a>
    </x-ff.page-header>

    <div class="ff-table-wrap">
        <div class="ff-table-toolbar">
            <form method="GET"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search posts…') }}" aria-label="{{ __('Search posts') }}"></form>
        </div>
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Post') }}</th><th>{{ __('Category') }}</th><th>{{ __('Author') }}</th><th>{{ __('Published') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($posts as $post)
                        <tr>
                            <td class="d-flex align-items-center gap-2">
                                @if ($post->cover_url)<img src="{{ $post->cover_url }}" alt="" class="rounded-2 ff-cover" style="width:56px;height:40px">@endif
                                <span><span class="fw-semibold text-ff-black d-block">{{ $post->title }}</span><span class="small-xs text-ff-muted">/blog/{{ $post->slug }} · {{ $post->reading_minutes }} {{ __('min read') }}</span></span>
                            </td>
                            <td class="small">{{ $post->category }}</td>
                            <td class="small">{{ $post->author?->name ?? '—' }}</td>
                            <td class="small">{{ $post->published_at?->format('j M Y') ?? '—' }}</td>
                            <td><x-ff.status :status="$post->status === 'published' && $post->published_at?->isFuture() ? 'scheduled' : $post->status" /></td>
                            <td class="text-end text-nowrap">
                                @if ($post->status === 'published')<a href="{{ route('blog.show', $post) }}" class="btn btn-sm btn-ff-ghost" target="_blank" aria-label="{{ __('View') }}"><i class="bi bi-eye"></i></a>@endif
                                <a href="{{ route('super-admin.blog.edit', $post) }}" class="btn btn-sm btn-ff-soft">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('super-admin.blog.destroy', $post) }}" class="d-inline" data-ff-confirm="{{ __('Delete this post?') }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ff.empty-state icon="bi-journal-richtext" :title="__('No posts yet')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($posts->hasPages())<div class="ff-table-footer">{{ $posts->links() }}</div>@endif
    </div>
</x-layouts.super-admin>
