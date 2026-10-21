<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChurchSummaryResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\LiveStreamResource;
use App\Http\Resources\PrayerRequestResource;
use App\Http\Resources\SermonResource;
use App\Models\ContentView;
use App\Models\Event;
use App\Models\LiveStream;
use App\Models\Post;
use App\Models\Sermon;
use App\Services\FeedService;
use App\Services\SermonBrowser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

/**
 * Feed, sermons, events and live services for the signed-in member.
 */
class ContentController extends Controller
{
    public function feed(Request $request, FeedService $feed): JsonResponse
    {
        $page = $feed->forUser($request->user(), null, $request->query('before'));

        return response()->json([
            'data' => $page['items']->map(fn ($item) => [
                'type' => $item['type'],
                'at' => $item['at']->toIso8601String(),
                'church' => new ChurchSummaryResource($item['church']),
                'item' => match ($item['type']) {
                    'sermon' => new SermonResource($item['model']),
                    'event' => new EventResource($item['model']),
                    'live' => new LiveStreamResource($item['model']),
                    'prayer' => new PrayerRequestResource($item['model']),
                    default => $this->plainItem($item['type'], $item['model']),
                },
            ])->values(),
            'next_before' => $page['next'],
        ]);
    }

    /**
     * Feed items that have no resource of their own: title and text, plus the few extras the app draws
     * (scripture for devotionals, the urgent flag for announcements, photos for posts, the author of a testimony).
     *
     * @return array<string, mixed>
     */
    protected function plainItem(string $type, Model $model): array
    {
        $base = [
            'id' => $model->getKey(),
            'title' => $model->title ?? null,
            'body' => strip_tags((string) ($model->body ?? $model->content ?? '')),
        ];

        return match ($type) {
            'devotional' => $base + ['scripture' => $model->scripture, 'scripture_text' => $model->scripture_text, 'cover_url' => $model->picture_url, 'web_url' => route('devotionals.show', $model)],
            'announcement' => $base + ['urgent' => $model->priority === 'urgent'],
            'post' => $base + ['images' => $model->getMedia('images')->take(4)->map(fn ($media) => $media->getUrl('feed'))->values()->all()],
            'testimony' => $base + ['author' => $model->is_anonymous ? null : $model->user?->name],
            default => $base,
        };
    }

    /** One update from a church with all its photos (the feed shows only the first few). */
    public function post(Request $request, Post $post): JsonResponse
    {
        $memberIds = $request->user()->memberChurchIds();
        abort_unless($post->status === 'published' && $post->published_at?->isPast() && ($post->visibility === 'public' || in_array($post->church_id, $memberIds, true)), 404);
        $post->loadMissing('church.media');

        return response()->json(['data' => [
            'id' => $post->id,
            'body' => strip_tags((string) $post->body),
            'published_at' => $post->published_at->toIso8601String(),
            'church' => new ChurchSummaryResource($post->church),
            'images' => $post->getMedia('images')->map(fn ($media) => $media->getUrl('feed'))->values()->all(),
        ]]);
    }

    public function sermons(Request $request): AnonymousResourceCollection
    {
        $memberIds = $request->user()->memberChurchIds();

        return SermonResource::collection(Sermon::with(['church.media', 'media', 'series'])
            ->published()->visibleTo($memberIds)
            ->whereIn('church_id', $request->filled('church') ? [(int) $request->query('church')] : ($memberIds ?: [0]))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', '%'.$request->query('q').'%')->orWhere('preacher', 'like', '%'.$request->query('q').'%')))
            ->when($request->filled('preacher'), fn ($q) => $q->where('preacher', (string) $request->query('preacher')))
            ->when($request->filled('series'), fn ($q) => $q->where('sermon_series_id', (int) $request->query('series')))
            ->latest('published_at')->paginate(min(50, max(1, (int) $request->query('per_page', 20))))->withQueryString());
    }

    /** The Sermons page in one request: the banner, the preachers, the newest, the most listened and the series. */
    public function sermonsBrowse(Request $request, SermonBrowser $browser): JsonResponse
    {
        return response()->json(['data' => $browser->sections($request->user(), $request)]);
    }

    public function sermon(Request $request, Sermon $sermon): SermonResource
    {
        $this->authorize('view', $sermon);

        return new SermonResource($sermon->load(['church.media', 'media', 'series']));
    }

    /**
     * The app calls this when a member starts playing a sermon. One member counts once in half an hour, so pressing
     * play again and again does not push a sermon up the "most listened" list.
     */
    public function listen(Request $request, Sermon $sermon): JsonResponse
    {
        $this->authorize('view', $sermon);

        if (Cache::add('sermon-listen.'.$request->user()->id.'.'.$sermon->id, true, now()->addMinutes(30))) {
            ContentView::create(['church_id' => $sermon->church_id, 'viewable_type' => $sermon->getMorphClass(), 'viewable_id' => $sermon->getKey(), 'user_id' => $request->user()->id, 'action' => 'listen', 'seconds' => 0]);
            $sermon->increment('listens_count');
        }

        return response()->json(['listens' => (int) $sermon->fresh()->listens_count]);
    }

    public function events(Request $request): AnonymousResourceCollection
    {
        $memberIds = $request->user()->memberChurchIds();

        return EventResource::collection(Event::with(['church.media', 'media'])
            ->published()->upcoming()->visibleTo($memberIds)
            ->whereIn('church_id', $request->filled('church') ? [(int) $request->query('church')] : ($memberIds ?: [0]))
            ->orderBy('starts_at')->paginate(20)->withQueryString());
    }

    public function event(Event $event): EventResource
    {
        $this->authorize('view', $event);

        return new EventResource($event->load(['church.media', 'media']));
    }

    public function live(Request $request): AnonymousResourceCollection
    {
        return LiveStreamResource::collection(LiveStream::with('church.media')
            ->whereIn('church_id', $request->user()->memberChurchIds() ?: [0])
            ->where(fn ($q) => $q->where('status', 'live')->orWhere(fn ($s) => $s->where('status', 'scheduled')->where('scheduled_at', '>=', now())))
            ->orderByRaw("status = 'live' DESC")->orderBy('scheduled_at')->take(20)->get());
    }
}
