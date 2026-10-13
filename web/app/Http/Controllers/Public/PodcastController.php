<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Approved podcasts on the public website: a page that lists them, a page for each one with its episodes and a player,
 * and a small player that can be embedded (an iframe) on any other website. Following and notes live in the app.
 */
class PodcastController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $category = (string) $request->query('category');

        $podcasts = Podcast::listed()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', '%'.$q.'%')->orWhere('host_name', 'like', '%'.$q.'%')->orWhere('summary', 'like', '%'.$q.'%')))
            ->when(array_key_exists($category, Podcast::CATEGORIES), fn ($query) => $query->where('category', $category))
            ->with('media')->orderByDesc('latest_episode_at')->orderByDesc('id')->paginate(18)->withQueryString();

        return view('public.podcasts.index', ['podcasts' => $podcasts, 'q' => $q, 'category' => $category]);
    }

    public function show(string $slug): View
    {
        $podcast = $this->find($slug);

        return view('public.podcasts.show', ['podcast' => $podcast, 'episodes' => $podcast->episodes()->out()->orderByDesc('published_at')->orderByDesc('id')->take(60)->get()]);
    }

    /** A small player for other websites. */
    public function embed(string $slug): View
    {
        $podcast = $this->find($slug);

        return view('public.podcasts.embed', ['podcast' => $podcast, 'episodes' => $podcast->episodes()->out()->orderByDesc('published_at')->orderByDesc('id')->take(12)->get()]);
    }

    protected function find(string $slug): Podcast
    {
        return Podcast::listed()->where('slug', $slug)->with('media')->firstOrFail();
    }
}
