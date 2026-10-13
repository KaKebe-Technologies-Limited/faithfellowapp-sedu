<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PrayerRequestResource;
use App\Models\Church;
use App\Models\PrayerInteraction;
use App\Models\PrayerRequest;
use App\Rules\NoContactDetails;
use App\Services\PrayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Prayer wall and notifications for the app.
 */
class CommunityController extends Controller
{
    public function __construct(protected PrayerService $prayers) {}

    public function prayers(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $memberIds = $user->memberChurchIds();

        $query = $request->query('status') === 'mine'
            ? PrayerRequest::with('church.media')->where('user_id', $user->id)
            : PrayerRequest::with('church.media')
                ->visibleTo($user, $memberIds, $user->pastoralChurchIds())
                ->whereIn('church_id', $memberIds ?: [0])
                ->where('status', $request->query('status') === 'answered' ? 'answered' : 'open');
        $page = $query->latest()->paginate(20)->withQueryString();

        $prayed = PrayerInteraction::where('user_id', $user->id)->where('type', 'prayed')->whereIn('prayer_request_id', $page->pluck('id'))->pluck('prayer_request_id')->all();
        $page->getCollection()->each(fn ($p) => $p->prayed_by_viewer = in_array($p->id, $prayed, true));

        return PrayerRequestResource::collection($page);
    }

    public function storePrayer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'church_id' => ['required', Rule::in($request->user()->memberChurchIds())],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'min:5', 'max:2000', new NoContactDetails],
            'visibility' => ['required', Rule::in(['public', 'church', 'pastor'])],
            'is_anonymous' => ['boolean'],
            'chain_enabled' => ['boolean'],
        ]);

        $prayer = $this->prayers->submit($request->user(), Church::findOrFail($data['church_id']), $data);

        return (new PrayerRequestResource($prayer->load('church.media')))->response()->setStatusCode(201);
    }

    public function prayer(Request $request, PrayerRequest $prayer): PrayerRequestResource
    {
        $this->authorize('view', $prayer);
        $prayer->load('church.media');
        $prayer->prayed_by_viewer = PrayerInteraction::where('prayer_request_id', $prayer->id)->where('user_id', $request->user()->id)->where('type', 'prayed')->exists();

        return new PrayerRequestResource($prayer);
    }

    public function answered(Request $request, PrayerRequest $prayer): PrayerRequestResource
    {
        $this->authorize('update', $prayer);
        $data = $request->validate(['answer_note' => ['nullable', 'string', 'max:2000']]);
        $this->prayers->markAnswered($prayer, $data['answer_note'] ?? null);

        return new PrayerRequestResource($prayer->fresh()->load('church.media'));
    }

    public function destroyPrayer(Request $request, PrayerRequest $prayer): JsonResponse
    {
        $this->authorize('update', $prayer);
        $prayer->delete();

        return response()->json(['deleted' => true]);
    }

    public function pray(Request $request, PrayerRequest $prayer): JsonResponse
    {
        $this->authorize('pray', $prayer);

        return response()->json($this->prayers->togglePrayed($prayer, $request->user()));
    }

    public function notifications(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn ($n) => [
                'id' => $n->id,
                'category' => $n->category,
                'church_id' => $n->church_id,
                'title' => $n->data['title'] ?? null,
                'body' => $n->data['body'] ?? null,
                'url' => $n->data['url'] ?? null,
                'kind' => $n->data['kind'] ?? null,
                'ref' => $n->data['ref'] ?? null,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'unread' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function readNotifications(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()
            ->when($request->filled('ids'), fn ($q) => $q->whereIn('id', (array) $request->input('ids')))
            ->update(['read_at' => now()]);

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }
}
