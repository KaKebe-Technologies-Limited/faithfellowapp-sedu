<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Models\PodcastEpisode;
use App\Models\RadioStation;
use App\Services\PodcastService;
use App\Services\RadioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Podcasts in the app: browse and search faith-based podcasts, follow them and play their episodes. Only podcasts the
 * super admin approved are listed. The audio is played from the link the podcaster added.
 */
class PodcastController extends Controller
{
    public function __construct(protected PodcastService $podcasts) {}

    /** The landing screen: categories, what you follow, the newest episodes and the newest podcasts. */
    public function overview(Request $request, RadioService $radio): JsonResponse
    {
        $user = $request->user();
        $followed = $user->followedPodcasts()->listed()->orderByDesc('latest_episode_at')->take(20)->get();
        $newest = PodcastEpisode::out()->whereHas('podcast', fn ($p) => $p->listed())->with('podcast')->orderByDesc('published_at')->take(12)->get();

        return response()->json(['data' => [
            'ours' => $this->ours($user, $radio),
            'categories' => collect(Podcast::CATEGORIES)->map(fn ($label, $key) => ['key' => $key, 'label' => __($label), 'count' => Podcast::listed()->where('category', $key)->count()])->filter(fn ($c) => $c['count'] > 0)->values(),
            // A shelf for each category that has podcasts (Faith and Motivation first): up to six, the newest first, and how many there are in all.
            'sections' => $this->sections($followed),
            'following' => $followed->map(fn (Podcast $p) => $this->card($p, true))->values(),
            'new_episodes' => $newest->map(fn (PodcastEpisode $e) => $this->episode($e))->values(),
            'podcasts' => Podcast::listed()->orderByDesc('latest_episode_at')->orderByDesc('id')->take(12)->get()->map(fn (Podcast $p) => $this->card($p, $followed->contains('id', $p->id)))->values(),
        ]]);
    }

    /**
     * "Our Podcasts": the teaching and podcasts the member's own churches have put out, a block for each church with its newest episodes first, to play
     * one by one on demand. Only when none of their churches has any does the platform's own podcast collection stand in, so the section is not empty
     * while the app has teaching to play.
     *
     * @return list<array<string, mixed>>
     */
    protected function ours($user, RadioService $radio): array
    {
        return rescue(function () use ($user, $radio) {
            if (! RadioStation::ready()) {
                return [];
            }
            $talk = $radio->stationsFor($user)->where('kind', 'talk')->values();
            $mine = $talk->filter(fn (RadioStation $s) => $s->church_id);

            return ($mine->isNotEmpty() ? $mine : $talk->reject(fn (RadioStation $s) => $s->church_id))->take(4)->map(function (RadioStation $s) use ($radio) {
                $episodes = $radio->tracks($s)->sortByDesc('id')->values();

                return [
                    'slug' => $s->slug,
                    'name' => $s->church?->name ?? $radio->name($s),
                    'church' => $s->church ? ['id' => $s->church->id, 'slug' => $s->church->slug, 'name' => $s->church->name, 'logo_url' => $s->church->logo_url] : null,
                    'total' => $episodes->count(),
                    'episodes' => $episodes->take(20)->map(fn ($t) => $t->toPayload())->all(),
                ];
            })->values()->all();
        }, [], false);
    }

    /**
     * @param  Collection<int, Podcast>  $followed
     * @return list<array{key: string, label: string, count: int, podcasts: list<array<string, mixed>>}>
     */
    protected function sections($followed): array
    {
        $byCategory = Podcast::listed()->orderByDesc('latest_episode_at')->orderByDesc('id')->take(300)->get()->groupBy('category');

        return collect(Podcast::CATEGORIES)->map(fn ($label, $key) => ['key' => $key, 'label' => __($label), 'count' => ($byCategory[$key] ?? collect())->count(),
            'podcasts' => ($byCategory[$key] ?? collect())->take(6)->map(fn (Podcast $p) => $this->card($p, $followed->contains('id', $p->id)))->values()->all()])
            ->filter(fn (array $s) => $s['count'] > 0)->values()->all();
    }

    /** Search and browse: `q` looks in the name, host and summary; `category` narrows it. */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:80'], 'category' => ['nullable', Rule::in(array_keys(Podcast::CATEGORIES))], 'following' => ['nullable', 'boolean']]);
        $followed = $request->user()->followedPodcasts()->pluck('podcasts.id')->all();

        $page = Podcast::listed()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')->orWhere('host_name', 'like', '%'.$request->query('q').'%')->orWhere('summary', 'like', '%'.$request->query('q').'%')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->when($request->boolean('following'), fn ($q) => $q->whereIn('id', $followed ?: [0]))
            ->orderByDesc('latest_episode_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (Podcast $p) => $this->card($p, in_array($p->id, $followed, true)))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, Podcast $podcast): JsonResponse
    {
        abort_unless($podcast->isListed(), 404);
        $episodes = $podcast->episodes()->out()->orderByDesc('published_at')->orderByDesc('id')->paginate(30);

        return response()->json(['data' => array_merge($this->card($podcast, $podcast->followers()->whereKey($request->user()->id)->exists()), [
            'episodes_count' => (int) $podcast->episodes_count,
            'description' => $podcast->description,
            'website_url' => $podcast->website_url,
            'language' => $podcast->language,
            'episodes' => collect($episodes->items())->map(fn (PodcastEpisode $e) => $this->episode($e, $podcast))->values(),
            'episodes_last_page' => $episodes->lastPage(),
        ])]);
    }

    public function episodes(Podcast $podcast): JsonResponse
    {
        abort_unless($podcast->isListed(), 404);
        $page = $podcast->episodes()->out()->orderByDesc('published_at')->orderByDesc('id')->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (PodcastEpisode $e) => $this->episode($e, $podcast))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function follow(Request $request, Podcast $podcast): JsonResponse
    {
        abort_unless($podcast->isListed(), 404);
        $this->podcasts->follow($podcast, $request->user());

        return response()->json(['following' => true, 'followers' => $podcast->fresh()->followers_count]);
    }

    public function unfollow(Request $request, Podcast $podcast): JsonResponse
    {
        $this->podcasts->unfollow($podcast, $request->user());

        return response()->json(['following' => false, 'followers' => $podcast->fresh()->followers_count]);
    }

    /** The app calls this when an episode starts, so podcasters can see how often they are heard. */
    public function played(PodcastEpisode $episode): JsonResponse
    {
        abort_unless($episode->status === 'published' && $episode->podcast?->isListed(), 404);
        $this->podcasts->played($episode);

        return response()->json(['plays' => $episode->fresh()->plays_count]);
    }

    /** @return array<string, mixed> */
    public function card(Podcast $p, bool $following): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'host' => $p->host_name,
            'summary' => $p->summary,
            'category' => $p->category,
            'category_label' => $p->categoryLabel(),
            'cover_url' => $p->coverUrl(),
            'episodes' => (int) $p->episodes_count,
            'followers' => (int) $p->followers_count,
            'latest_at' => $p->latest_episode_at?->toIso8601String(),
            'following' => $following,
        ];
    }

    /** @return array<string, mixed> */
    protected function episode(PodcastEpisode $e, ?Podcast $podcast = null): array
    {
        $podcast ??= $e->podcast;

        return [
            'id' => $e->id,
            'title' => $e->title,
            'summary' => $e->summary,
            'audio_url' => $e->audio_url,
            'duration' => $e->duration_seconds,
            'season' => $e->season,
            'number' => $e->number,
            'published_at' => $e->published_at?->toIso8601String(),
            'podcast' => ['id' => $podcast->id, 'name' => $podcast->name, 'host' => $podcast->host_name, 'cover_url' => $podcast->coverUrl()],
        ];
    }
}
