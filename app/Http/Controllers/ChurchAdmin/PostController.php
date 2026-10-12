<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Post;
use App\Services\ContentPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Church feed posts — short updates and photos shown in members' feeds.
 */
class PostController extends Controller
{
    public function index(Church $church): View
    {
        return view('church-admin.posts.index', [
            'church' => $church,
            'posts' => $church->posts()->with(['author', 'media', 'branch'])->orderByDesc('is_pinned')->latest('published_at')->paginate(15),
            'branches' => $church->branches()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', Rule::in(['public', 'members'])],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('church_id', $church->id)],
            'published_at' => ['nullable', 'date'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $post = $church->posts()->create([
            'body' => $data['body'],
            'visibility' => $data['visibility'],
            'branch_id' => $data['branch_id'] ?? null,
            'user_id' => $request->user()->id,
            'status' => 'published',
            'published_at' => $data['published_at'] ?? now(),
        ]);

        foreach ($request->file('images', []) as $image) {
            $post->addMedia($image)->toMediaCollection('images');
        }

        // Followers get an in-app (and push, per their settings) alert; scheduled posts alert when they go live.
        app(ContentPublisher::class)->announceIfLive($post->load('church'));

        return back()->with('success', $post->published_at->isFuture() ? __('Post scheduled.') : __('Posted to your members’ feeds.'));
    }

    public function update(Request $request, Church $church, Post $post): RedirectResponse
    {
        $data = $request->validate([
            'is_pinned' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['published', 'hidden'])],
        ]);
        $post->update($data);

        return back()->with('success', __('Post updated.'));
    }

    public function destroy(Church $church, Post $post): RedirectResponse
    {
        $post->delete();

        return back()->with('success', __('Post deleted.'));
    }
}
