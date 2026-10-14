<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChurchResource;
use App\Http\Resources\ChurchSummaryResource;
use App\Models\Church;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
