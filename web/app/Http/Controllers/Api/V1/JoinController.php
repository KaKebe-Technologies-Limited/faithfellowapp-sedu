<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChurchSummaryResource;
use App\Models\Church;
use App\Services\JoinLinks;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A church's join link in the app. The link (https://faithfellows.com/join/CODE) opens the app on a screen that shows the church; once the person has
 * an account the app follows the church for them. The code can also be typed, or found again by the app after it was installed from the link.
 */
class JoinController extends Controller
{
    /** Which church a code (or a pasted link) belongs to. Public: the app asks before there is an account. */
    public function show(string $code, JoinLinks $links): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->church($code, $links))]);
    }

    /** Follows the church of the link for the signed-in member. Asks the church to approve when it approves new members. */
    public function follow(Request $request, string $code, JoinLinks $links, SubscriptionService $subscriptions): JsonResponse
    {
        $church = $this->church($code, $links);
        $subscription = $subscriptions->subscribe($request->user(), $church, 'invite');

        return response()->json([
            'status' => $subscription->status,
            'message' => $subscription->status === 'pending'
                ? __('Request sent. :church approves new members.', ['church' => $church->name])
                : __('You now follow :church.', ['church' => $church->name]),
            'data' => $this->payload($church),
        ]);
    }

    /**
     * The first time the app opens after being installed: the church whose link this phone opened a moment ago, if it is certain which one.
     * The app asks the person before using the answer, because the address is shared by many phones.
     */
    public function match(Request $request, JoinLinks $links): JsonResponse
    {
        $platform = (string) $request->validate(['platform' => ['required', 'in:android,ios']])['platform'];
        $church = rescue(fn () => $links->match($request, $platform), null, false);

        return response()->json(['data' => $church ? $this->payload($church) : null]);
    }

    protected function church(string $code, JoinLinks $links): Church
    {
        return $links->find($code) ?? abort(404, __('We could not find a church with that code. Check it and try again.'));
    }

    /** @return array<string, mixed> */
    protected function payload(Church $church): array
    {
        return [
            'code' => $church->join_code,
            'church' => [
                ...(new ChurchSummaryResource($church))->resolve(),
                'tagline' => $church->tagline,
                'city' => $church->city,
                'denomination' => $church->denomination,
                'followers' => $church->subscriptions()->where('status', 'active')->count(),
            ],
            'requires_approval' => (bool) $church->requires_member_approval,
        ];
    }
}
