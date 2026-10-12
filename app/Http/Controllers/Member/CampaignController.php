<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Comment;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $churchIds = $request->user()->memberChurchIds();

        $campaigns = Campaign::with(['church.media', 'media'])
            ->where(fn ($q) => $q->forChurches($churchIds)->orWhere(fn ($f) => $f->where('is_featured', true)->where('visibility', 'public')))
            ->whereHas('church', fn ($c) => $c->where('status', 'active'))
            ->active()->visibleTo($churchIds)
            ->orderByDesc('is_featured')->latest()
            ->paginate(12);

        return view('member.campaigns.index', compact('campaigns'));
    }

    /**
     * Public campaign page — visitors can view; giving requires sign-in.
     */
    public function show(Request $request, Campaign $campaign, CampaignService $service): View
    {
        $this->authorize('view', $campaign);

        $campaign->load([
            'church.media', 'media', 'milestones', 'teams',
            'updates' => fn ($q) => $q->with('media')->whereNotNull('published_at')->take(10),
            'comments' => fn ($q) => $q->with('user.media')->visible()->whereNull('parent_id')->latest()->take(30),
        ]);

        $url = route('campaigns.show', $campaign);

        return view('member.campaigns.show', [
            'campaign' => $campaign,
            'leaderboard' => $service->leaderboard($campaign),
            'recent' => $campaign->donations()->with('user')->succeeded()->latest('paid_at')->take(8)->get(),
            'qr' => QrCode::format('svg')->size(220)->margin(1)->generate($url.'?ref=qr'),
            'shareUrl' => $url,
            'celebrate' => $campaign->goal_reached_at?->gt(now()->subDays(3)) ?? false,
        ]);
    }

    public function comment(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('view', $campaign);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        Comment::create([
            'church_id' => $campaign->church_id,
            'commentable_type' => $campaign->getMorphClass(),
            'commentable_id' => $campaign->id,
            'user_id' => $request->user()->id,
            'body' => strip_tags($data['body']),
        ]);

        return back()->with('success', __('Thank you for your encouragement!'));
    }

    /** Printable QR poster for offline giving at events. */
    public function poster(Campaign $campaign): View
    {
        $this->authorize('view', $campaign);
        $campaign->load('church.media');

        return view('member.campaigns.poster', [
            'campaign' => $campaign,
            'qr' => QrCode::format('svg')->size(420)->margin(1)->errorCorrection('H')->generate(route('campaigns.show', $campaign).'?ref=qr'),
        ]);
    }
}
