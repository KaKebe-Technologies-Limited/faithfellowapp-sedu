<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChurchResource;
use App\Models\Church;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
