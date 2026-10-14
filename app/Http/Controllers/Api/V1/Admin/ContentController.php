<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\AnnouncementRequest;
use App\Http\Requests\Content\DevotionalRequest;
use App\Http\Requests\Content\SermonRequest;
use App\Models\Announcement;
use App\Models\Church;
use App\Models\Devotional;
use App\Models\Sermon;
use App\Services\ContentPublisher;
use App\Services\SermonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Publishing from the phone: announcements, devotionals and sermons (with links to audio or video).
 * The same rules as the website — drafts stay private, a future date schedules, publishing notifies followers.
 */
class ContentController extends Controller
{
    public function __construct(protected ContentPublisher $publisher) {}

    // ── Announcements ────────────────────────────────────────────────────────────────────────────────────

    public function announcements(Church $church): JsonResponse
    {
        return response()->json(['data' => $church->announcements()->latest('published_at')->latest('id')->take(40)->get()->map(fn (Announcement $a) => [
            'id' => $a->id, 'title' => $a->title, 'body' => plain_text($a->body), 'audience' => $a->audience, 'urgent' => $a->priority === 'urgent',
            'status' => $a->status, 'published_at' => $a->published_at?->toIso8601String(),
        ])->values()]);
    }

    public function storeAnnouncement(AnnouncementRequest $request, Church $church): JsonResponse
    {
        $data = Announcement::resolvePublishing($request->safe()->except('image'));
        $data['body'] = ff_safe_html(nl2br(e($data['body'])));
        $data['channels'] = $data['channels'] ?? [];

        $announcement = $church->announcements()->create([...$data, 'user_id' => $request->user()->id]);
        if ($request->hasFile('image')) {
            $announcement->addMediaFromRequest('image')->toMediaCollection('image');
        }
        $this->publisher->announceIfLive($announcement->load('church'));

        return response()->json(['data' => ['id' => $announcement->id, 'status' => $announcement->status]], 201);
    }

    public function destroyAnnouncement(Church $church, Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(['deleted' => true]);
    }

    // ── Devotionals ──────────────────────────────────────────────────────────────────────────────────────

    public function devotionals(Church $church): JsonResponse
    {
        return response()->json(['data' => $church->devotionals()->latest('publish_date')->take(40)->get()->map(fn (Devotional $d) => [
            'id' => $d->id, 'title' => $d->title, 'scripture' => $d->scripture, 'publish_date' => $d->publish_date?->toDateString(), 'status' => $d->status,
        ])->values()]);
    }

    public function storeDevotional(DevotionalRequest $request, Church $church): JsonResponse
    {
        $data = $request->safe()->except('audio');
        $data['body'] = ff_safe_html(nl2br(e($data['body'])));

        $devotional = $church->devotionals()->create([...$data, 'user_id' => $request->user()->id]);
        $this->publisher->announceIfLive($devotional->load('church'));

        return response()->json(['data' => ['id' => $devotional->id, 'status' => $devotional->status]], 201);
    }

    public function destroyDevotional(Church $church, Devotional $devotional): JsonResponse
    {
        $devotional->delete();

        return response()->json(['deleted' => true]);
    }

    // ── Sermons ──────────────────────────────────────────────────────────────────────────────────────────

    public function sermons(Church $church): JsonResponse
    {
        return response()->json(['data' => $church->sermons()->latest('published_at')->latest('id')->take(40)->get()->map(fn (Sermon $s) => [
            'id' => $s->id, 'title' => $s->title, 'preacher' => $s->preacher, 'status' => $s->status, 'published_at' => $s->published_at?->toIso8601String(),
        ])->values()]);
    }

    public function storeSermon(SermonRequest $request, Church $church, SermonService $sermons): JsonResponse
    {
        $sermon = $sermons->save($church, $request, null, $request->user());

        return response()->json(['data' => ['id' => $sermon->id, 'status' => $sermon->status]], 201);
    }

    public function destroySermon(Request $request, Church $church, Sermon $sermon): JsonResponse
    {
        $sermon->delete();

        return response()->json(['deleted' => true]);
    }
}
