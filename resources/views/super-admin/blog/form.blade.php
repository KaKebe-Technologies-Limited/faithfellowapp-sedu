<x-layouts.super-admin :title="$post->exists ? __('Edit post') : __('New post')">
    <x-ff.page-header :title="$post->exists ? __('Edit post') : __('New post')" :breadcrumbs="[__('Blog') => route('super-admin.blog.index'), ($post->exists ? Str::limit($post->title, 30) : __('New')) => null]" />

    <form method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route('super-admin.blog.update', $post) : route('super-admin.blog.store') }}" class="row g-4" novalidate>
        @csrf
        @if ($post->exists) @method('PUT') @endif
        <div class="col-lg-8">
            <div class="ff-card">
                <x-ff.input name="title" :label="__('Title')" :value="$post->title" required maxlength="200" />
                <x-ff.textarea name="excerpt" :label="__('Summary')" :value="$post->excerpt" rows="2" maxlength="400" :help="__('Shown on blog cards and in search results. Leave empty to use the first lines.')" />
                <x-ff.textarea name="body" :label="__('Article')" :value="$post->body" editor required />
            </div>
        </div>
        <div class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <x-ff.select name="status" :label="__('Status')" :options="['draft' => __('Draft'), 'published' => __('Published')]" :value="$post->status" />
                <x-ff.input type="datetime-local" name="published_at" :label="__('Publish date')" :value="$post->published_at?->format('Y-m-d\TH:i')" :help="__('Future dates schedule the post.')" />
                <label class="form-label" for="category">{{ __('Category') }}</label>
                <input name="category" id="category" list="categories" class="form-control mb-3" value="{{ old('category', $post->category) }}" required maxlength="60">
                <datalist id="categories">@foreach ($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
                <x-ff.input name="slug" :label="__('URL slug')" :value="$post->slug" prepend="/blog/" :help="__('Leave empty to create from the title.')" />
            </div>
            <div class="ff-card">
                @if ($post->cover_url)<img src="{{ $post->cover_url }}" alt="" class="w-100 rounded-3 mb-2 ff-cover" style="aspect-ratio: 16/9">@endif
                <label class="form-label" for="cover">{{ __('Cover image') }}</label>
                <input type="file" id="cover" name="cover" accept="image/*" class="form-control @error('cover') is-invalid @enderror">
                @error('cover')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button class="btn btn-ff-primary btn-lg">{{ $post->exists ? __('Save changes') : __('Save post') }}</button>
        </div>
    </form>
</x-layouts.super-admin>
