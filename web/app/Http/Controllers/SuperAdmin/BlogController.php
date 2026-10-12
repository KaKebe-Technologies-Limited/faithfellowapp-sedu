<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Platform blog (public /blog): ministry resources, product news, SEO content.
 */
class BlogController extends Controller
{
    public function index(Request $request): View
    {
        return view('super-admin.blog.index', [
            'posts' => BlogPost::with(['author', 'media'])
                ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->query('q').'%'))
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new BlogPost(['status' => 'draft', 'category' => 'Resources']));
    }

    public function store(Request $request): RedirectResponse
    {
        $post = BlogPost::create([...$this->validated($request), 'author_id' => $request->user()->id]);
        $this->saveCover($request, $post);

        return redirect()->route('super-admin.blog.index')->with('success', __('Post saved.'));
    }

    public function edit(BlogPost $blogPost): View
    {
        return $this->form($blogPost);
    }

    public function update(Request $request, BlogPost $blogPost): RedirectResponse
    {
        $blogPost->update($this->validated($request, $blogPost));
        $this->saveCover($request, $blogPost);

        return redirect()->route('super-admin.blog.index')->with('success', __('Post updated.'));
    }

    public function destroy(BlogPost $blogPost): RedirectResponse
    {
        $blogPost->delete();

        return back()->with('success', __('Post deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?BlogPost $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'alpha_dash', 'max:200', Rule::unique('blog_posts', 'slug')->ignore($post?->id)],
            'category' => ['required', 'string', 'max:60'],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'body' => ['required', 'string'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'max:4096'],
        ]);

        $body = ff_safe_html($data['body']);

        return [
            'title' => $data['title'],
            'slug' => $data['slug'] ?: ($post?->slug ?? $this->uniqueSlug($data['title'])),
            'category' => $data['category'],
            'excerpt' => $data['excerpt'] ?: Str::limit(trim(strip_tags($body)), 200),
            'body' => $body,
            'reading_minutes' => max(1, (int) ceil(str_word_count(strip_tags($body)) / 200)),
            'status' => $data['status'],
            'published_at' => $data['status'] === 'published'
                ? ($data['published_at'] ? Carbon::parse($data['published_at']) : ($post?->published_at ?? now()))
                : null,
        ];
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        for ($i = 2; BlogPost::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    protected function saveCover(Request $request, BlogPost $post): void
    {
        if ($request->hasFile('cover')) {
            $post->addMediaFromRequest('cover')->toMediaCollection('cover');
        }
    }

    protected function form(BlogPost $post): View
    {
        return view('super-admin.blog.form', [
            'post' => $post,
            'categories' => BlogPost::distinct()->orderBy('category')->pluck('category')->merge(['Resources', 'Product updates', 'Church growth', 'Digital ministry'])->unique()->values(),
        ]);
    }
}
