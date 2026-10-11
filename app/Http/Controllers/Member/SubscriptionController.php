<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\Notifications\PreferenceResolver;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptions) {}

    /**
     * "My churches" — everything the user follows.
     */
    public function index(Request $request, PreferenceResolver $prefs): View
    {
        $subscriptions = $request->user()->subscriptions()
            ->whereIn('status', ['active', 'pending'])
            ->with(['church.media', 'church.serviceTimes'])
            ->latest('subscribed_at')
            ->get();

        $muted = $subscriptions->mapWithKeys(fn ($s) => [
            $s->church_id => collect(array_keys(config('faithfellows.notification_categories')))
                ->every(fn ($c) => $prefs->channelsFor($request->user(), $s->church_id, $c) === []),
        ]);

        return view('member.churches.index', compact('subscriptions', 'muted'));
    }

    public function store(Request $request, Church $church): RedirectResponse|JsonResponse
    {
        $subscription = $this->subscriptions->subscribe($request->user(), $church, $request->input('source', 'directory'));

        $message = $subscription->status === 'pending'
            ? __('Request sent — :church will approve your membership shortly.', ['church' => $church->name])
            : __('You are now following :church.', ['church' => $church->name]);

        return $request->expectsJson()
            ? response()->json(['status' => $subscription->status, 'message' => $message])
            : back()->with('success', $message);
    }

    public function destroy(Request $request, Church $church): RedirectResponse|JsonResponse
    {
        $this->subscriptions->unsubscribe($request->user(), $church);
        $message = __('You have unsubscribed from :church.', ['church' => $church->name]);

        return $request->expectsJson()
            ? response()->json(['status' => 'left', 'message' => $message])
            : back()->with('success', $message);
    }

    public function mute(Request $request, Church $church, PreferenceResolver $prefs): RedirectResponse
    {
        abort_unless($request->user()->isSubscribedTo($church), 403);
        $muted = $request->boolean('muted');
        $prefs->setChurchMuted($request->user(), $church->id, $muted);

        return back()->with('success', $muted
            ? __('Notifications from :church are muted.', ['church' => $church->name])
            : __('Notifications from :church are on.', ['church' => $church->name]));
    }
}
