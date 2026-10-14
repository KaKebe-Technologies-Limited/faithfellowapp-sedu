<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ChurchBroadcast;
use App\Models\ContentReport;
use App\Models\PrayerRequest;
use App\Models\Subscription;
use App\Services\ModerationService;
use App\Services\Notifications\BroadcastService;
use App\Services\PrayerService;
use App\Services\SubscriptionService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The people side of running a church from the app: the prayer queue, member approvals, messages to the whole
 * church, and the moderation queue.
 */
class CommunityController extends Controller
{
    public function __construct(protected PrayerService $prayers, protected SubscriptionService $subscriptions, protected ModerationService $moderation, protected BroadcastService $broadcasts) {}

    // ── Prayer ───────────────────────────────────────────────────────────────────────────────────────────

    public function prayers(Request $request, Church $church): JsonResponse
    {
        $status = $request->query('status', 'pending');
        $page = $church->prayerRequests()->with(['user.media', 'responder'])
            ->when($status === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($status === 'private', fn ($q) => $q->where('visibility', 'pastor')->whereNotIn('status', ['hidden']))
            ->when($status === 'wall', fn ($q) => $q->whereIn('status', PrayerRequest::SHARED)->where('visibility', '!=', 'pastor'))
            ->when(in_array($status, ['declined', 'hidden'], true), fn ($q) => $q->where('status', $status))
            ->latest()->paginate(25)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (PrayerRequest $p) => [
                'id' => $p->id, 'title' => $p->title, 'body' => $p->body, 'status' => $p->status, 'visibility' => $p->visibility,
                'author' => ['name' => $p->user?->name, 'avatar_url' => $p->user?->avatar_url, 'anonymous' => (bool) $p->is_anonymous],
                'prayed_count' => (int) $p->prayed_count, 'response' => $p->pastor_response, 'created_at' => $p->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'counts' => [
                'pending' => $church->prayerRequests()->where('status', 'pending')->count(),
                'private' => $church->prayerRequests()->where('visibility', 'pastor')->where('status', 'open')->whereNull('responded_at')->count(),
            ],
        ]);
    }

    public function approvePrayer(Request $request, Church $church, PrayerRequest $prayerRequest): JsonResponse
    {
        $this->prayers->approve($prayerRequest, $request->user());
        Audit::log('prayer.approved', $prayerRequest);

        return response()->json(['status' => $prayerRequest->fresh()->status]);
    }

    public function declinePrayer(Request $request, Church $church, PrayerRequest $prayerRequest): JsonResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        $this->prayers->decline($prayerRequest, $request->user(), $note);
        Audit::log('prayer.declined', $prayerRequest);

        return response()->json(['status' => $prayerRequest->fresh()->status]);
    }

    public function respondPrayer(Request $request, Church $church, PrayerRequest $prayerRequest): JsonResponse
    {
        $data = $request->validate(['response' => ['required', 'string', 'max:3000']]);
        $this->prayers->respond($prayerRequest, $request->user(), $data['response']);
        Audit::log('prayer.responded', $prayerRequest);

        return response()->json(['responded' => true]);
    }

    public function hidePrayer(Church $church, PrayerRequest $prayerRequest): JsonResponse
    {
        $prayerRequest->update(['status' => 'hidden']);
        Audit::log('prayer.hidden', $prayerRequest);

        return response()->json(['status' => 'hidden']);
    }

    // ── Members ──────────────────────────────────────────────────────────────────────────────────────────

    public function members(Request $request, Church $church): JsonResponse
    {
        $page = $church->subscriptions()->with(['user.media'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')), fn ($q) => $q->whereIn('status', ['active', 'pending']))
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->query('stage')))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$request->query('q').'%')->orWhere('phone', 'like', '%'.$request->query('q').'%')))
            ->latest('subscribed_at')->paginate(30)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (Subscription $s) => [
                'id' => $s->id, 'name' => $s->user?->name, 'avatar_url' => $s->user?->avatar_url, 'city' => $s->user?->city, 'phone' => $s->user?->phone,
                'stage' => $s->stage, 'stage_label' => Subscription::STAGES[$s->stage] ?? $s->stage, 'status' => $s->status, 'since' => $s->subscribed_at?->toDateString(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'counts' => $church->subscriptions()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'stages' => collect(Subscription::STAGES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ]);
    }

    public function updateMember(Request $request, Church $church, Subscription $subscription): JsonResponse
    {
        $data = $request->validate([
            'action' => ['nullable', Rule::in(['approve', 'block', 'unblock'])],
            'stage' => ['nullable', Rule::in(array_keys(Subscription::STAGES))],
        ]);

        match ($data['action'] ?? null) {
            'approve' => $this->subscriptions->approve($subscription),
            'block' => $this->subscriptions->block($subscription),
            'unblock' => $this->subscriptions->unblock($subscription),
            default => $subscription->update(array_filter(['stage' => $data['stage'] ?? null], fn ($v) => $v !== null)),
        };

        return response()->json(['status' => $subscription->fresh()->status, 'stage' => $subscription->fresh()->stage]);
    }

    // ── Messages to the church ───────────────────────────────────────────────────────────────────────────

    public function broadcasts(Church $church): JsonResponse
    {
        return response()->json(['data' => $church->churchBroadcasts()->latest()->take(30)->get()->map(fn (ChurchBroadcast $b) => [
            'id' => $b->id, 'title' => $b->title, 'body' => $b->body, 'status' => $b->status, 'recipients' => (int) $b->recipients_count,
            'sent_at' => $b->sent_at?->toIso8601String(), 'scheduled_at' => $b->scheduled_at?->toIso8601String(),
        ])->values(), 'categories' => collect(config('faithfellows.notification_categories'))->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()]);
    }

    public function estimate(Request $request, Church $church): JsonResponse
    {
        [$audience, $options] = $this->audience($request, $church);

        return response()->json(['data' => $this->broadcasts->estimate($church->id, $audience, $options)]);
    }

    public function sendBroadcast(Request $request, Church $church): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:2000'],
            'category' => ['required', Rule::in(array_keys(config('faithfellows.notification_categories')))],
            'is_urgent' => ['boolean'],
            'send' => ['required', Rule::in(['now', 'later'])],
            'scheduled_at' => ['nullable', 'required_if:send,later', 'date', 'after:now'],
        ]);
        [$audience, $options] = $this->audience($request, $church);

        $broadcast = $church->churchBroadcasts()->create([
            'user_id' => $request->user()->id, 'title' => $data['title'], 'body' => strip_tags($data['body']), 'audience' => $audience,
            'audience_options' => $options ?: null, 'channels' => ['webpush', 'mail'], 'category' => $data['category'], 'is_urgent' => $request->boolean('is_urgent'),
            'status' => $data['send'] === 'later' ? 'scheduled' : 'draft', 'scheduled_at' => $data['send'] === 'later' ? Carbon::parse($data['scheduled_at']) : null,
        ]);
        if ($data['send'] === 'now') {
            $this->broadcasts->send($broadcast);
        }

        return response()->json(['data' => ['id' => $broadcast->id, 'status' => $broadcast->fresh()->status, 'recipients' => (int) $broadcast->fresh()->recipients_count]], 201);
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    protected function audience(Request $request, Church $church): array
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(array_keys(ChurchBroadcast::AUDIENCES))],
            'small_group_id' => ['nullable', 'required_if:audience,group', Rule::exists('small_groups', 'id')->where('church_id', $church->id)],
            'stages' => ['array', 'required_if:audience,stage'],
            'stages.*' => [Rule::in(['visitor', 'new_member', 'member', 'worker', 'leader'])],
        ]);

        return [$data['audience'], array_filter([
            'small_group_id' => $data['audience'] === 'group' ? (int) $data['small_group_id'] : null,
            'stages' => $data['audience'] === 'stage' ? array_values($data['stages'] ?? []) : null,
        ])];
    }

    // ── Moderation ───────────────────────────────────────────────────────────────────────────────────────

    public function reports(Request $request, Church $church): JsonResponse
    {
        $page = $church->contentReports()->with(['reportable', 'reporter'])->where('status', $request->query('status', 'open'))->latest()->paginate(25)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (ContentReport $r) => [
                'id' => $r->id, 'reason' => $r->reason, 'reason_label' => ContentReport::REASONS[$r->reason] ?? $r->reason, 'details' => $r->details, 'status' => $r->status,
                'type' => class_basename($r->reportable_type),
                'content' => str(strip_tags((string) ($r->reportable->body ?? $r->reportable->title ?? '')))->limit(280)->toString(),
                'reporter' => $r->reporter?->name, 'created_at' => $r->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function hideReported(Request $request, Church $church, ContentReport $contentReport): JsonResponse
    {
        $this->moderation->hide($contentReport, $request->user());

        return response()->json(['status' => $contentReport->fresh()->status]);
    }

    public function dismissReport(Request $request, Church $church, ContentReport $contentReport): JsonResponse
    {
        $this->moderation->dismiss($contentReport, $request->user());

        return response()->json(['status' => $contentReport->fresh()->status]);
    }
}
