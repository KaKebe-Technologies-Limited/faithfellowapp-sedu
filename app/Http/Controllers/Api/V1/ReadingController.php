<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\DevotionalResource;
use App\Models\Announcement;
use App\Models\Campaign;
use App\Models\Comment;
use App\Models\Devotional;
use App\Rules\NoContactDetails;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The things a member reads in the app: devotionals, announcements and giving campaigns.
 */
class ReadingController extends Controller
{
    public function devotionals(Request $request): AnonymousResourceCollection
    {
        $memberIds = $request->user()->memberChurchIds();

        return DevotionalResource::collection(Devotional::with(['church.media'])->published()->visibleTo($memberIds)
            ->whereIn('church_id', $request->filled('church') ? [(int) $request->query('church')] : ($memberIds ?: [0]))
            ->orderByDesc('publish_date')->paginate(15)->withQueryString());
    }

    public function devotional(Devotional $devotional): DevotionalResource
    {
        $this->authorize('view', $devotional);

        return new DevotionalResource($devotional->load(['church.media', 'media', 'author']));
    }

    public function announcements(Request $request): AnonymousResourceCollection
    {
        $memberIds = $request->user()->memberChurchIds();

        return AnnouncementResource::collection(Announcement::with(['church.media', 'media'])->current()->forAudience($memberIds)
            ->whereIn('church_id', $memberIds ?: [0])
            ->orderByRaw("priority = 'urgent' DESC")->latest('published_at')->paginate(15)->withQueryString());
    }

    public function announcement(Announcement $announcement): AnnouncementResource
    {
        $this->authorize('view', $announcement);

        return new AnnouncementResource($announcement->load(['church.media', 'media']));
    }

    /** Active campaigns of the member's churches, plus featured public ones from elsewhere. */
    public function campaigns(Request $request): AnonymousResourceCollection
    {
        $memberIds = $request->user()->memberChurchIds();

        return CampaignResource::collection(Campaign::with(['church.media', 'media'])
            ->where(fn ($q) => $q->whereIn('church_id', $memberIds)->orWhere(fn ($f) => $f->where('is_featured', true)->where('visibility', 'public')))
            ->whereHas('church', fn ($c) => $c->where('status', 'active'))
            ->active()->visibleTo($memberIds)
            ->when($request->filled('church'), fn ($q) => $q->where('church_id', (int) $request->query('church')))
            ->orderByDesc('is_featured')->latest()->paginate(12)->withQueryString());
    }

    public function campaign(Campaign $campaign): CampaignResource
    {
        $this->authorize('view', $campaign);
        rescue(fn () => app(CampaignService::class)->healCampaign($campaign), null, false);

        return new CampaignResource($campaign->load([
            'church.media', 'media', 'milestones',
            'updates' => fn ($q) => $q->whereNotNull('published_at')->take(10),
        ]));
    }

    public function comment(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000', new NoContactDetails]]);

        $comment = Comment::create([
            'church_id' => $campaign->church_id,
            'commentable_type' => $campaign->getMorphClass(),
            'commentable_id' => $campaign->id,
            'user_id' => $request->user()->id,
            'body' => strip_tags($data['body']),
        ]);

        return response()->json(['data' => ['id' => $comment->id, 'author' => $request->user()->name, 'body' => $comment->body, 'at' => $comment->created_at?->toIso8601String()]], 201);
    }
}
