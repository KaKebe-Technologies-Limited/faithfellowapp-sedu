<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Church;
use App\Models\Sermon;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        $stats = Cache::remember('landing_stats', 600, fn () => $this->platformStats());
        $posts = BlogPost::published()->with('media')->latest('published_at')->take(3)->get();

        return view('public.home', compact('stats', 'posts'));
    }

    public function about(): View
    {
        return view('public.about');
    }

    public function services(): View
    {
        return view('public.services');
    }

    public function pricing(): View
    {
        return view('public.pricing');
    }

    public function privacy(): View
    {
        return view('public.privacy');
    }

    public function terms(): View
    {
        return view('public.terms');
    }

    public function offline(): View
    {
        return view('public.offline');
    }

    /**
     * Headline numbers for the landing page. Later sprints add real counts.
     *
     * @return array<string, int>
     */
    protected function platformStats(): array
    {
        $count = fn (string $class, ?callable $scope = null) => class_exists($class)
            ? rescue(fn () => ($scope ? $scope($class::query()) : $class::query())->count(), 0, false)
            : 0;

        return [
            'churches' => $count(Church::class, fn ($q) => $q->where('status', 'active')),
            'members' => $count(User::class),
            'sermons' => $count(Sermon::class),
        ];
    }
}
