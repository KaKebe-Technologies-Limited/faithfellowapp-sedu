<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\FeedService;
use App\Services\HomeSlider;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Member home: a feed of everything happening in the user's churches.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, FeedService $feed): View
    {
        $user = $request->user();
        $filterChurch = $request->filled('church')
            ? Church::where('slug', $request->query('church'))->first()
            : null;

        $result = $feed->forUser($user, $filterChurch ? [$filterChurch->id] : null, $request->query('before'));

        $suggested = $user->memberChurchIds() === [] || count($user->subscribedChurchIds()) < 3
            ? Church::active()->with('media')->whereNotIn('id', $user->subscribedChurchIds())
                ->orderByDesc('is_featured')->when($user->city, fn ($q) => $q->orderByRaw('city = ? desc', [$user->city]))
                ->take(4)->get()
            : collect();

        return view('member.dashboard', [
            'user' => $user,
            'slides' => app(HomeSlider::class)->forUser($user, $filterChurch),
            'items' => $result['items'],
            'next' => $result['next'],
            'filterChurch' => $filterChurch,
            'suggested' => $suggested,
            'widgets' => method_exists($feed, 'sidebarWidgets') ? $feed->sidebarWidgets($user) : [],
        ]);
    }
}
