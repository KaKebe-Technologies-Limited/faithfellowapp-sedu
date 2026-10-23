<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChurchSummaryResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\LiveStreamResource;
use App\Http\Resources\PrayerRequestResource;
use App\Http\Resources\SermonResource;
use App\Models\Announcement;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\Devotional;
use App\Models\Event;
use App\Models\LiveStream;
use App\Models\Podcast;
use App\Models\PrayerInteraction;
use App\Models\PrayerRequest;
use App\Models\QuizPlayer;
use App\Models\QuizSession;
use App\Models\Sermon;
use App\Services\Bible\BibleService;
use App\Services\HomeSlider;
use App\Services\InspirationService;
use App\Support\VerseOfTheDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Everything the app's home screen shows above the feed, in one request: the banner slider,
 * the member's churches, what is live now, and short "highlight" cards (announcements, the next
 * events, today's devotional, giving campaigns, the latest sermons). The scrolling feed itself
 * stays on GET /feed.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, HomeSlider $slider, BibleService $bible, InspirationService $inspiration): JsonResponse
    {
        $user = $request->user();
        $memberIds = $user->memberChurchIds();
        $churchIds = $memberIds ?: [0];

        $followed = Church::active()->with('media')->whereIn('id', $user->subscribedChurchIds() ?: [0])->orderBy('name')->get();

        $live = LiveStream::with('church.media')
            ->whereIn('church_id', $churchIds)
            ->where(fn ($q) => $q->where('status', 'live')->orWhere(fn ($s) => $s->where('status', 'scheduled')->where('scheduled_at', '>=', now())))
            ->orderByRaw("status = 'live' DESC")->orderBy('scheduled_at')->take(3)->get();
        $liveChurchIds = $live->where('status', 'live')->pluck('church_id')->all();

        $events = Event::with(['church.media', 'media'])->published()->upcoming()->visibleTo($memberIds)
            ->whereIn('church_id', $churchIds)->orderBy('starts_at')->take(4)->get();

        $sermons = Sermon::with(['church.media', 'media', 'series'])->published()->visibleTo($memberIds)
            ->when($memberIds, fn ($q) => $q->whereIn('church_id', $memberIds), fn ($q) => $q->where('visibility', 'public'))
            ->latest('published_at')->take(4)->get();

        $campaigns = Campaign::with(['church.media', 'media'])->active()->visibleTo($memberIds)
            ->whereIn('church_id', $churchIds)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest()->take(3)->get();

        // The most recent devotional from the last week (a stale one would feel wrong as "today's").
        $devotional = Devotional::with('church.media')->published()->visibleTo($memberIds)
            ->whereIn('church_id', $churchIds)->whereDate('publish_date', '>=', today()->subDays(7))
            ->orderByDesc('publish_date')->first();

        $announcements = Announcement::with('church.media')->current()->forAudience($memberIds)
            ->whereIn('church_id', $churchIds)
            ->orderByRaw("priority = 'urgent' DESC")->latest('published_at')->take(3)->get();

        // The two sermons members listened to most (all time, across their churches); newest first when nothing has been played yet.
        $topSermons = Sermon::with(['church.media', 'media', 'series'])->published()->visibleTo($memberIds)
            ->whereIn('church_id', $churchIds)->orderByDesc('listens_count')->orderByDesc('views_count')->latest('published_at')->take(2)->get();

        // Prayer requests the member may see, newest first, and which of them they have already prayed for.
        $prayers = PrayerRequest::with('church.media')->visibleTo($user, $memberIds, $user->pastoralChurchIds())
            ->whereIn('church_id', $churchIds)->where('status', 'open')->latest()->take(3)->get();
        $prayed = PrayerInteraction::where('user_id', $user->id)->where('type', 'prayed')->whereIn('prayer_request_id', $prayers->pluck('id'))->pluck('prayer_request_id')->all();
        foreach ($prayers as $p) {
            $p->prayed_by_viewer = in_array($p->id, $prayed, true);
        }

        $games = QuizSession::active()->with(['quiz', 'church.media'])->withCount('players')->whereIn('church_id', $churchIds)->latest()->take(2)->get();
        $played = QuizPlayer::where('user_id', $user->id);

        return response()->json(['data' => [
            'slides' => $this->slides($user, $slider, $followed),
            'highlights' => $this->highlights($live, $events, $campaigns, $devotional, $sermons),
            'inspiration' => rescue(fn () => $inspiration->current(), null, false),
            'podcasts' => rescue(fn () => Podcast::listed()->orderByDesc('followers_count')->orderByDesc('episodes_count')->orderByDesc('id')->take(4)->get()
                ->map(fn (Podcast $p) => app(PodcastController::class)->card($p, $user->followedPodcasts()->whereKey($p->id)->exists()))->values(), [], false),
            'churches' => $followed->map(fn (Church $c) => [
                'id' => $c->id, 'slug' => $c->slug, 'name' => $c->name, 'logo_url' => $c->logo_url, 'city' => $c->city, 'is_live' => in_array($c->id, $liveChurchIds, true),
                'join_url' => $c->join_code ? route('join.show', $c->join_code) : null,
            ])->values(),
            'live' => LiveStreamResource::collection($live),
            'announcements' => $announcements->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => Str::limit(trim(strip_tags((string) $a->body)), 160),
                'urgent' => $a->priority === 'urgent',
                'published_at' => $a->published_at?->toIso8601String(),
                'church' => new ChurchSummaryResource($a->church),
            ])->values(),
            'events' => EventResource::collection($events),
            'devotional' => $devotional ? [
                'id' => $devotional->id,
                'title' => $devotional->title,
                'scripture' => $devotional->scripture,
                'scripture_text' => $devotional->scripture_text,
                'cover_url' => $devotional->picture_url,
                'excerpt' => Str::limit(trim(strip_tags((string) $devotional->body)), 180),
                'publish_date' => $devotional->publish_date?->toDateString(),
                'church' => new ChurchSummaryResource($devotional->church),
                'web_url' => route('devotionals.show', $devotional),
            ] : null,
            'campaigns' => $campaigns->map(fn (Campaign $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'description' => Str::limit(trim(strip_tags((string) $c->short_description)), 140),
                'cover_url' => $c->picture_url,
                'goal' => (float) $c->goal_amount,
                'raised' => (float) $c->raised_amount,
                'percent' => $c->percent(),
                'currency' => $c->currency,
                'givers' => (int) $c->givers_count,
                'ends_at' => $c->ends_at?->toIso8601String(),
                'church' => new ChurchSummaryResource($c->church),
                'web_url' => route('campaigns.show', $c),
            ])->values(),
            'sermons' => SermonResource::collection($sermons),
            'top_sermons' => $topSermons->map(fn (Sermon $s) => [...(new SermonResource($s))->resolve($request), 'listens' => (int) $s->listens_count])->values(),
            'verse' => rescue(fn () => VerseOfTheDay::forUser($user, $bible), null, false),
            'prayers' => [
                'items' => PrayerRequestResource::collection($prayers),
                'open_count' => PrayerRequest::visibleTo($user, $memberIds, $user->pastoralChurchIds())->whereIn('church_id', $churchIds)->where('status', 'open')->count(),
            ],
            'quiz' => [
                'live' => $games->map(fn (QuizSession $g) => [
                    'id' => $g->id, 'quiz' => $g->quiz->title, 'players_count' => (int) $g->players_count, 'status' => $g->status, 'church' => new ChurchSummaryResource($g->church),
                ])->values(),
                'played' => (clone $played)->count(),
                'best_score' => (int) (clone $played)->max('score'),
            ],
        ]]);
    }

    /**
     * What the slider at the top of Home shows: the live service first, then the next events, giving campaigns, today's devotional and the
     * latest sermons, each with a picture (the church's own, or a generated cover) and a line of words. Words only, no icons.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function highlights(Collection $live, Collection $events, Collection $campaigns, ?Devotional $devotional, Collection $sermons): array
    {
        $items = collect();
        foreach ($live->where('status', 'live') as $s) {
            $items->push(['kind' => 'live', 'id' => $s->id, 'label' => __('Live now'), 'title' => $s->title, 'subtitle' => $s->church?->name, 'image' => $s->church?->cover_url, 'church' => $s->church?->name]);
        }
        foreach ($events as $e) {
            $items->push(['kind' => 'event', 'id' => $e->id, 'label' => __('Event'), 'title' => $e->title, 'subtitle' => collect([$e->starts_at?->format('D j M, g:i A'), $e->location_name ?? null])->filter()->implode(' · '), 'image' => $e->picture_url, 'church' => $e->church?->name]);
        }
        foreach ($campaigns as $c) {
            $items->push(['kind' => 'campaign', 'id' => $c->id, 'label' => __('Fundraising'), 'title' => $c->title, 'subtitle' => Str::limit(trim(strip_tags((string) $c->short_description)), 90), 'image' => $c->picture_url, 'church' => $c->church?->name]);
        }
        if ($devotional) {
            $items->push(['kind' => 'devotional', 'id' => $devotional->id, 'label' => __('Devotional'), 'title' => $devotional->title, 'subtitle' => $devotional->scripture, 'image' => $devotional->picture_url, 'church' => $devotional->church?->name]);
        }
        foreach ($sermons->take(2) as $s) {
            $items->push(['kind' => 'sermon', 'id' => $s->id, 'label' => __('Sermon'), 'title' => $s->title, 'subtitle' => $s->preacher, 'image' => $s->picture_url, 'church' => $s->church?->name]);
        }

        return $items->filter(fn (array $i) => filled($i['title']))->take(10)->values()->all();
    }

    /**
     * The banner slider: the churches' slider photos, taking turns between churches. A church that
     * has not uploaded enough slides still gets a banner from its cover photo, so a new member never
     * sees an empty top of the screen.
     *
     * @param  Collection<int, Church>  $followed
     * @return array<int, array<string, mixed>>
     */
    protected function slides($user, HomeSlider $slider, $followed): array
    {
        $summary = fn (Church $c) => ['id' => $c->id, 'slug' => $c->slug, 'name' => $c->name, 'logo_url' => $c->logo_url];
        $byId = $followed->keyBy('id');

        $slides = $slider->forUser($user)->map(fn (array $s) => [
            'kind' => 'slide',
            'image' => $s['image'],
            'image_sm' => $s['image_sm'],
            'title' => $s['title'],
            'subtitle' => $s['subtitle'],
            'church' => isset($byId[$s['church_id']]) ? $summary($byId[$s['church_id']]) : null,
        ])->values();

        if ($slides->isEmpty()) {
            $slides = $followed->filter(fn (Church $c) => $c->cover_url)->take(6)->map(fn (Church $c) => [
                'kind' => 'cover',
                'image' => $c->cover_url,
                'image_sm' => $c->cover_url,
                'title' => $c->name,
                'subtitle' => $c->tagline,
                'church' => $summary($c),
            ])->values();
        }

        return $slides->all();
    }
}
