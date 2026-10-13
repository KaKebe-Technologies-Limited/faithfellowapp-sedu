<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Post;
use App\Services\ContentPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Updates with photos for the church's feed, written and posted from the phone: a few lines and up to six pictures.
 * Followers are told about a new post (unless it is scheduled for later, when they are told as it goes live).
 */
class PostController extends Controller
{
    public function __construct(protected ContentPublisher $publisher) {}

    public function index(Church $church): JsonResponse
    {
        return response()->json(['data' => $church->posts()->with(['media', 'author'])->orderByDesc('is_pinned')->latest('published_at')->take(40)->get()->map(fn (Post $p) => $this->card($p))->values()]);
    }

    public function store(Request $request, Church $church): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000', 'required_without:images'],
            'visibility' => ['required', Rule::in(['public', 'members'])],
            'published_at' => ['nullable', 'date'],
            'is_pinned' => ['sometimes', 'boolean'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $post = $church->posts()->create([
            'body' => trim((string) ($data['body'] ?? '')),
            'visibility' => $data['visibility'],
            'user_id' => $request->user()->id,
            'status' => 'published',
            'is_pinned' => $request->boolean('is_pinned'),
            'published_at' => $data['published_at'] ?? now(),
        ]);

        foreach ($request->file('images', []) as $image) {
            $post->addMedia($image)->toMediaCollection('images');
        }

        $this->publisher->announceIfLive($post->load('church'));

        return response()->json(['data' => $this->card($post->fresh(['media', 'author']))], 201);
    }

    public function update(Request $request, Church $church, Post $post): JsonResponse
    {
        $data = $request->validate([
            'body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'visibility' => ['sometimes', Rule::in(['public', 'members'])],
            'is_pinned' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['published', 'hidden'])],
        ]);
        $post->update($data);

        return response()->json(['data' => $this->card($post->fresh(['media', 'author']))]);
    }

    public function destroy(Church $church, Post $post): JsonResponse
    {
        $post->delete();

        return response()->json(['deleted' => true]);
    }

    /** @return array<string, mixed> */
    protected function card(Post $p): array
    {
        return [
            'id' => $p->id,
            'body' => $p->body,
            'visibility' => $p->visibility,
            'status' => $p->status,
            'is_pinned' => (bool) $p->is_pinned,
            'published_at' => $p->published_at?->toIso8601String(),
            'scheduled' => (bool) $p->published_at?->isFuture(),
            'images' => $p->getMedia('images')->map(fn ($m) => $m->getUrl('feed'))->values()->all(),
            'author' => $p->author?->name,
        ];
    }
}
