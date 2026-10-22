<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChurchResource;
use App\Http\Resources\ChurchSummaryResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\LiveStreamResource;
use App\Http\Resources\SermonResource;
use App\Models\Church;
use App\Models\RadioStation;
use App\Services\ChurchPageService;
use App\Services\RadioService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChurchController extends Controller
{
    /**
     * Public church directory.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $churches = Church::active()->with('media')->withCount(['subscriptions' => fn ($q) => $q->where('status', 'active')])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')
                ->orWhere('pastor_name', 'like', '%'.$request->query('q').'%')))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->query('city')))
            ->when($request->filled('denomination'), fn ($q) => $q->where('denomination', $request->query('denomination')))
            ->orderByDesc('is_featured')->orderBy('name')
            ->paginate(min(50, (int) $request->query('per_page', 20)))->withQueryString();

        return ChurchResource::collection($churches);
    }

    /**
     * Churches close to the member, nearest first, from the (rounded) place they chose to share. `radius` is in kilometres.
     * Answers `has_location: false` until the member has shared a place.
     */
    public function nearby(Request $request): JsonResponse
    {
        $data = $request->validate(['radius' => ['nullable', 'integer', Rule::in([5, 10, 25, 50, 100, 200])]]);
        $me = $request->user();
        if ($me->latitude === null) {
            return response()->json(['data' => [], 'has_location' => false]);
        }

        $radius = (int) ($data['radius'] ?? 50);
        [$lat, $lng] = [(float) $me->latitude, (float) $me->longitude];
        $box = $radius / 111.0; // one degree of latitude is about 111 km
        $distance = '(6371 * acos(least(1, cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))))';
        $mine = $me->subscribedChurchIds();

        $churches = Church::active()->with('media')->whereNotNull('lat')->whereNotNull('lng')->whereBetween('lat', [$lat - $box, $lat + $box])
            ->selectRaw("churches.*, {$distance} as distance_km", [$lat, $lng, $lat])
            ->havingRaw('distance_km <= ?', [$radius])->orderBy('distance_km')->limit(20)->get();

        return response()->json(['has_location' => true, 'radius' => $radius, 'data' => $churches->map(fn (Church $c) => [
            ...(new ChurchSummaryResource($c))->resolve($request),
            'city' => $c->city,
            'denomination' => $c->denomination,
            'distance_km' => round((float) $c->distance_km, 1),
            'distance_label' => match (true) {
                $c->distance_km < 1 => __('Less than 1 km away'),
                $c->distance_km < 10 => __('About :km km away', ['km' => (int) round($c->distance_km)]),
                default => __('About :km km away', ['km' => (int) (round($c->distance_km / 5) * 5)]),
            },
            'following' => in_array($c->id, $mine, true),
        ])->values()]);
    }

    public function show(Church $church): ChurchResource
    {
        abort_unless($church->isActive(), 404);

        return new ChurchResource($church->load(['media', 'serviceTimes', 'givingChannels'])
            ->loadCount(['subscriptions' => fn ($q) => $q->where('status', 'active')]));
    }

    /**
     * Everything the church's page in the app shows besides its details, in one request: the live service, the sermons (with their audio), the upcoming
     * events, giving campaigns, the devotional, updates and notices, the church's own radio channel and its songs and teaching, the photos and the
     * social links. People who follow the church (or work for it) also get what the church keeps for its members.
     */
    public function page(Request $request, Church $church, ChurchPageService $pages, RadioService $radio): JsonResponse
    {
        abort_unless($church->isActive(), 404);
        $viewer = $request->user('sanctum');
        $church->loadMissing('media');
        $s = $pages->sections($church, $viewer);
        $isMember = (bool) $s['isMember'] || (bool) $s['isStaff'];

        $live = fn ($stream) => $stream ? (new LiveStreamResource($stream->setRelation('church', $church)))->resolve($request) : null;
        $announcements = $church->announcements()->current()->forAudience($isMember ? [$church->id] : [])
            ->orderByRaw("priority = 'urgent' DESC")->latest('published_at')->take(3)->get();

        return response()->json(['data' => [
            'stats' => [
                'followers' => $s['subscriberCount'],
                'sermons' => $church->sermons()->published()->when(! $isMember, fn ($q) => $q->where('visibility', 'public'))->count(),
                'events' => $church->events()->published()->upcoming()->when(! $isMember, fn ($q) => $q->where('visibility', 'public'))->count(),
            ],
            'live' => $live($s['liveNow']),
            'next_live' => $live($s['nextLive']),
            'sermons' => SermonResource::collection($s['sermons'])->resolve($request),
            'events' => EventResource::collection($s['events'])->resolve($request),
            'campaigns' => $s['campaigns']->map(fn ($c) => [
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
            ])->values(),
            'devotional' => $s['devotional'] ? [
                'id' => $s['devotional']->id,
                'title' => $s['devotional']->title,
                'scripture' => $s['devotional']->scripture,
                'scripture_text' => $s['devotional']->scripture_text,
                'cover_url' => $s['devotional']->picture_url,
                'excerpt' => Str::limit(trim(strip_tags((string) $s['devotional']->body)), 220),
                'publish_date' => $s['devotional']->publish_date?->toDateString(),
            ] : null,
            'updates' => $s['posts']->map(fn ($p) => [
                'id' => $p->id,
                'body' => strip_tags((string) $p->body),
                'images' => $p->getMedia('images')->take(4)->map(fn ($media) => $media->getUrl('feed'))->values()->all(),
                'author' => $p->author?->name,
                'is_pinned' => (bool) $p->is_pinned,
                'published_at' => $p->published_at?->toIso8601String(),
            ])->values(),
            'announcements' => $announcements->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => Str::limit(trim(strip_tags((string) $a->body)), 220),
                'urgent' => $a->priority === 'urgent',
                'published_at' => $a->published_at?->toIso8601String(),
            ])->values(),
            'prayer' => ['open_count' => $s['prayer']->count()],
            'giving' => ['available' => $s['givingChannels']->isNotEmpty()],
            'channel' => $this->channel($church, $viewer, $radio),
            'gallery' => $church->getMedia('gallery')->take(12)->map(fn ($m) => ['url' => $m->getUrl(), 'thumb' => $m->getUrl('thumb')])->values(),
            'socials' => $church->socialLinks()->get()->map(fn ($l) => ['platform' => $l->platform, 'url' => $l->url])->values(),
        ]]);
    }

    /**
     * The church's own radio channel (audio that plays round the clock) and what is in it. Everyone sees that it exists; only the people who may
     * listen (they follow the church or work for it) get the list of songs and teaching to play one by one.
     *
     * @return array<string, mixed>|null
     */
    protected function channel(Church $church, $viewer, RadioService $radio): ?array
    {
        return rescue(function () use ($church, $viewer, $radio) {
            if (! RadioStation::ready()) {
                return null;
            }
            $station = RadioStation::where('slug', 'church-'.$church->id)->where('church_id', $church->id)->first();
            if (! $station || ! $radio->available($station)) {
                return null;
            }
            $tracks = $radio->tracks($station);
            if ($tracks->isEmpty()) {
                return null;
            }
            $canListen = $radio->canListen($viewer, $station);

            return [
                'slug' => $station->slug,
                'name' => $radio->name($station),
                'tagline' => $station->tagline(),
                'songs' => $tracks->count(),
                'can_listen' => $canListen,
                'tracks' => $canListen ? $tracks->take(60)->map(fn ($t) => $t->toPayload())->values() : [],
            ];
        }, null, false);
    }

    public function subscribe(Request $request, Church $church, SubscriptionService $subscriptions): JsonResponse
    {
        abort_unless($church->isActive(), 404);
        $subscription = $subscriptions->subscribe($request->user(), $church, 'api');

        return response()->json(['status' => $subscription->status, 'message' => $subscription->status === 'pending'
            ? __('Request sent. :church approves new members.', ['church' => $church->name])
            : __('You now follow :church.', ['church' => $church->name])], 201);
    }

    public function unsubscribe(Request $request, Church $church, SubscriptionService $subscriptions): JsonResponse
    {
        $subscriptions->unsubscribe($request->user(), $church);

        return response()->json(['message' => __('Unsubscribed.')]);
    }
}
