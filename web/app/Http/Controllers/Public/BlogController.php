<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $posts = BlogPost::published()
            ->with(['media', 'author'])
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', '%'.$request->string('q').'%')
                ->orWhere('excerpt', 'like', '%'.$request->string('q').'%')))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        $categories = BlogPost::published()->distinct()->orderBy('category')->pluck('category');

        return view('public.blog.index', compact('posts', 'categories'));
    }

    public function show(BlogPost $post): View
    {
        abort_unless($post->status === 'published' && $post->published_at?->isPast(), 404);

        $post->load(['media', 'author']);
        $related = BlogPost::published()->with('media')
            ->whereKeyNot($post->id)->where('category', $post->category)
            ->latest('published_at')->take(3)->get();

        return view('public.blog.show', compact('post', 'related'));
    }
}
