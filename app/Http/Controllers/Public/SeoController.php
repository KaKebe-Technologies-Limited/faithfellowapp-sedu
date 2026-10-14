<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\Event;
use App\Models\Sermon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * sitemap.xml (public pages, active churches, blog, public sermons/events/campaigns)
 * and robots.txt (blocks indexing outside production).
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHours(6), function () {
            $urls = collect([
                ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly'],
                ['loc' => route('about'), 'priority' => '0.6'],
                ['loc' => route('services'), 'priority' => '0.8'],
                ['loc' => route('pricing'), 'priority' => '0.8'],
                ['loc' => route('contact'), 'priority' => '0.5'],
                ['loc' => route('churches.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
                ['loc' => route('blog.index'), 'priority' => '0.7', 'changefreq' => 'weekly'],
                ['loc' => route('church.apply'), 'priority' => '0.7'],
            ]);

            Church::active()->select(['id', 'slug', 'updated_at'])->orderBy('id')->each(fn ($c) => $urls->push([
                'loc' => route('churches.show', $c), 'lastmod' => $c->updated_at, 'priority' => '0.8', 'changefreq' => 'weekly',
            ]));
            BlogPost::published()->select(['id', 'slug', 'updated_at'])->orderBy('id')->each(fn ($p) => $urls->push([
                'loc' => route('blog.show', $p), 'lastmod' => $p->updated_at, 'priority' => '0.6',
            ]));

            $activeChurches = Church::active()->select('id');
            Sermon::published()->where('visibility', 'public')->whereIn('church_id', $activeChurches)
                ->select(['id', 'updated_at'])->latest('published_at')->take(2000)->get()
                ->each(fn ($s) => $urls->push(['loc' => route('sermons.show', $s), 'lastmod' => $s->updated_at, 'priority' => '0.5']));
            Event::published()->upcoming()->where('visibility', 'public')->whereIn('church_id', $activeChurches)
                ->select(['id', 'updated_at'])->take(1000)->get()
                ->each(fn ($e) => $urls->push(['loc' => route('events.show', $e), 'lastmod' => $e->updated_at, 'priority' => '0.6']));
            Campaign::active()->where('visibility', 'public')->whereIn('church_id', $activeChurches)
                ->select(['id', 'slug', 'updated_at'])->get()
                ->each(fn ($c) => $urls->push(['loc' => route('campaigns.show', $c), 'lastmod' => $c->updated_at, 'priority' => '0.6']));

            return view('seo.sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? [
                'User-agent: *',
                'Disallow: /dashboard', 'Disallow: /church/', 'Disallow: /super-admin', 'Disallow: /profile',
                'Disallow: /notifications', 'Disallow: /give/', 'Disallow: /webhooks/', 'Disallow: /api/',
                'Disallow: /check-in/', 'Disallow: /prayer', 'Disallow: /counseling',
                '',
                'Sitemap: '.route('sitemap'),
            ]
            // Staging and local: keep everything out of search engines.
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
