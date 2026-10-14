<x-layouts.church-admin :church="$church" :title="__('Feed posts')">
    <x-ff.page-header :title="__('Feed posts')" :subtitle="__('Short updates and photos that appear in your members’ home feed.')" />

    <div class="row g-4">
        <div class="col-lg-5">
            <form method="POST" action="{{ route('church-admin.posts.store', $church) }}" enctype="multipart/form-data" class="ff-card" novalidate>
                @csrf
                <h2 class="h6">{{ __('New post') }}</h2>
                <x-ff.textarea name="body" :label="__('What’s happening?')" rows="5" maxlength="5000" required :placeholder="__('Thank you to everyone who joined the youth outreach on Saturday…')" />
                <div class="mb-3">
                    <label for="images" class="form-label">{{ __('Photos (up to 6)') }}</label>
                    <input type="file" name="images[]" id="images" multiple accept="image/jpeg,image/png,image/webp" class="form-control @error('images.*') is-invalid @enderror">
                    @error('images.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="row">
                    <div class="col-sm-6"><x-ff.select name="visibility" :label="__('Who can see it')" :options="['public' => __('Everyone'), 'members' => __('Members only')]" value="public" /></div>
                    <div class="col-sm-6"><x-ff.input name="published_at" type="datetime-local" :label="__('Schedule (optional)')" /></div>
                    @if ($branches->count() > 1)
                        <div class="col-12"><x-ff.select name="branch_id" :label="__('Branch')" :options="$branches" :placeholder="__('All branches')" /></div>
                    @endif
                </div>
                <button class="btn btn-ff-primary w-100"><i class="bi bi-send"></i> {{ __('Post') }}</button>
            </form>
        </div>
        <div class="col-lg-7 d-grid gap-3 align-content-start">
            @forelse ($posts as $post)
                <div class="ff-card">
                    <div class="d-flex align-items-center gap-2 mb-2 small">
                        <x-ff.avatar :user="$post->author" size="xs" />
                        <strong class="text-ff-black">{{ $post->author?->name }}</strong>
                        <span class="text-ff-muted">· {{ $post->published_at?->isFuture() ? __('Scheduled for :date', ['date' => $post->published_at->format('j M, H:i')]) : $post->published_at?->diffForHumans() }}</span>
                        @if ($post->is_pinned)<span class="ff-badge ff-badge-primary"><i class="bi bi-pin-angle"></i> {{ __('Pinned') }}</span>@endif
                        @if ($post->visibility === 'members')<span class="ff-badge">{{ __('Members') }}</span>@endif
                        @if ($post->status === 'hidden')<x-ff.status status="hidden" />@endif
                        @if ($post->branch)<span class="ff-badge">{{ $post->branch->name }}</span>@endif
                    </div>
                    <p class="mb-2" style="white-space: pre-line">{{ Str::limit($post->body, 400) }}</p>
                    @if ($post->media->isNotEmpty())
                        <div class="d-flex gap-2 mb-2">
                            @foreach ($post->media->take(4) as $image)
                                <img src="{{ $image->getUrl('feed') }}" alt="" class="rounded-3" style="width:72px;height:72px;object-fit:cover" loading="lazy">
                            @endforeach
                        </div>
                    @endif
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('church-admin.posts.update', [$church, $post]) }}">@csrf @method('PUT')
                            <input type="hidden" name="is_pinned" value="{{ $post->is_pinned ? 0 : 1 }}">
                            <button class="btn btn-sm btn-ff-soft"><i class="bi bi-pin"></i> {{ $post->is_pinned ? __('Unpin') : __('Pin') }}</button>
                        </form>
                        <form method="POST" action="{{ route('church-admin.posts.update', [$church, $post]) }}">@csrf @method('PUT')
                            <input type="hidden" name="status" value="{{ $post->status === 'hidden' ? 'published' : 'hidden' }}">
                            <button class="btn btn-sm btn-ff-ghost">{{ $post->status === 'hidden' ? __('Show') : __('Hide') }}</button>
                        </form>
                        <form method="POST" action="{{ route('church-admin.posts.destroy', [$church, $post]) }}" class="ms-auto" data-ff-confirm="{{ __('Delete this post?') }}">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            @empty
                <x-ff.empty-state icon="bi-postcard-heart" :title="__('No posts yet')" :message="__('Share news, photos and encouragement with your members.')" />
            @endforelse
            {{ $posts->links() }}
        </div>
    </div>
</x-layouts.church-admin>
