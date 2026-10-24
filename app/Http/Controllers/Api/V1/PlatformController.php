<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ContentReport;
use App\Models\Podcast;
use App\Models\RadioStation;
use App\Models\RadioTrack;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ChurchOnboardingService;
use App\Services\ModerationService;
use App\Services\PodcastService;
use App\Services\PushBroadcaster;
use App\Services\RadioService;
use App\Services\SubscriptionService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The platform admin running FaithFellows from the app with the same sign-in as on the website: what needs attention, church applications and
 * churches, putting people into churches, reports, podcast reviews, and what goes out to everyone (a notification, the radio). Everything that
 * is hard to undo asks for the admin's password again, so a phone that is left unlocked cannot do it; every action is written to the audit log.
 */
class PlatformController extends Controller
{
    public function __construct(
        protected ChurchOnboardingService $onboarding,
        protected SubscriptionService $subscriptions,
        protected ModerationService $moderation,
        protected PodcastService $podcasts,
        protected RadioService $radio,
    ) {}

    /** Asks for the password again before something that is hard to undo. (An account that signs in with Google only has none to ask for.) */
    protected function confirm(Request $request): void
    {
        $user = $request->user();
        if ($user->password && ! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => __('That password is not right.')]);
        }
    }

    public function overview(): JsonResponse
    {
        $churches = Church::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $reports = ContentReport::where('status', 'open');
        $broadcast = $this->radio->activeBroadcast();

        return response()->json(['data' => [
            'churches' => collect(Church::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($churches[$s] ?? 0)]),
            'users' => User::count(),
            'new_users_this_week' => User::where('created_at', '>=', now()->subWeek())->count(),
            'needs_attention' => [
                'applications' => (int) ($churches['pending'] ?? 0),
                'podcasts' => Podcast::where('status', 'pending')->count(),
                'reports' => (clone $reports)->count(),
                'old_reports' => (clone $reports)->where('created_at', '<', now()->subDays(2))->count(),
            ],
            'broadcast' => $broadcast ? ['title' => $broadcast['track']->title, 'ends_at' => date(DATE_ATOM, $broadcast['ends']), 'message' => $broadcast['message']] : null,
            'phones' => app(PushBroadcaster::class)->audience(),
        ]]);
    }

    // ── Churches ────────────────────────────────────────────────────────────────────────────────

    public function churches(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(Church::STATUSES)], 'q' => ['nullable', 'string', 'max:80']]);
        $page = Church::query()->with(['media', 'owner'])
            ->search($request->query('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByRaw("status = 'pending' desc")->latest()->paginate(20)->withQueryString();
        $followers = Subscription::whereIn('church_id', collect($page->items())->pluck('id'))->where('status', 'active')->selectRaw('church_id, count(*) as n')->groupBy('church_id')->pluck('n', 'church_id');

        return response()->json([
            'data' => collect($page->items())->map(fn (Church $c) => $this->church($c) + ['followers' => (int) ($followers[$c->id] ?? 0)])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function approveChurch(Church $church): JsonResponse
    {
        abort_unless($church->status === 'pending', 422, __(':church is not waiting for approval.', ['church' => $church->name]));
        $this->onboarding->approve($church);

        return response()->json(['data' => $this->church($church->refresh()), 'message' => __(':church approved and notified.', ['church' => $church->name])]);
    }

    public function declineChurch(Request $request, Church $church): JsonResponse
    {
        $this->confirm($request);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        abort_unless($church->status === 'pending', 422, __(':church is not waiting for approval.', ['church' => $church->name]));
        $this->onboarding->archive($church, $data['reason'] ?? null);

        return response()->json(['data' => $this->church($church->refresh()), 'message' => __(':church was declined.', ['church' => $church->name])]);
    }

    public function suspendChurch(Request $request, Church $church): JsonResponse
    {
        $this->confirm($request);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        abort_unless($church->status === 'active', 422, __(':church is not active.', ['church' => $church->name]));
        $this->onboarding->suspend($church, $data['reason'] ?? null);

        return response()->json(['data' => $this->church($church->refresh()), 'message' => __(':church has been suspended.', ['church' => $church->name])]);
    }

    public function reactivateChurch(Request $request, Church $church): JsonResponse
    {
        $this->confirm($request);
        abort_unless($church->status === 'suspended', 422, __(':church is not suspended.', ['church' => $church->name]));
        $this->onboarding->reactivate($church);

        return response()->json(['data' => $this->church($church->refresh()), 'message' => __(':church is active again.', ['church' => $church->name])]);
    }

    /** @return array<string, mixed> */
    protected function church(Church $c): array
    {
        return [
            'id' => $c->id, 'slug' => $c->slug, 'name' => $c->name, 'city' => $c->city, 'country' => $c->country, 'denomination' => $c->denomination,
            'status' => $c->status, 'status_reason' => $c->status_reason, 'logo_url' => $c->logo_url,
            'owner' => $c->owner ? ['name' => $c->owner->name, 'email' => $c->owner->email, 'phone' => $c->owner->phone] : null,
            'applied_at' => $c->created_at?->toIso8601String(),
        ];
    }

    // ── People ──────────────────────────────────────────────────────────────────────────────────

    public function users(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $q = trim((string) $request->query('q'));
        $page = User::query()->with('media')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
            ->latest()->paginate(20)->withQueryString();
        $follows = Subscription::whereIn('user_id', collect($page->items())->pluck('id'))->where('status', 'active')->with('church:id,name')->get()->groupBy('user_id');

        return response()->json([
            'data' => collect($page->items())->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'status' => $u->status, 'avatar_url' => $u->avatar_url,
                'joined_at' => $u->created_at?->toIso8601String(),
                'churches' => ($follows[$u->id] ?? collect())->map(fn ($s) => ['id' => $s->church_id, 'name' => $s->church?->name])->values(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Puts a registered user into one or more churches: they follow them at once, whatever a church's approval setting says. */
    public function assign(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'church_ids' => ['required', 'array', 'min:1', 'max:20'],
            'church_ids.*' => ['integer', 'exists:churches,id'],
            'stage' => ['nullable', Rule::in(array_keys(Subscription::STAGES))],
            'notify' => ['boolean'],
        ]);
        abort_unless($user->status === 'active', 422, __(':name is suspended, so cannot be added to a church.', ['name' => $user->name]));

        $result = [];
        foreach (Church::whereIn('id', $data['church_ids'])->orderBy('name')->get() as $church) {
            $result[] = ['church' => $church->name, 'result' => $this->subscriptions->assign($user, $church, $request->user(), $data['stage'] ?? 'member', $request->boolean('notify'))];
        }

        return response()->json(['data' => $result, 'message' => __(':name was updated.', ['name' => $user->name])]);
    }

    // ── Reports and podcast reviews ─────────────────────────────────────────────────────────────

    public function reports(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(['open', 'actioned', 'dismissed'])]]);
        $page = ContentReport::with(['reportable', 'reporter', 'church'])->where('status', $request->query('status', 'open'))->latest()->paginate(20)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(function (ContentReport $r) {
                $content = $r->reportable;
                $author = $this->moderation->author($content);

                return [
                    'id' => $r->id,
                    'kind' => array_search($r->reportable_type, ContentReport::TYPES, true) ?: class_basename($r->reportable_type),
                    'excerpt' => $content ? Str::limit((string) ($content->body ?? $content->title ?? $content->message ?? ''), 240) : __('(removed)'),
                    'reason' => __(ContentReport::REASONS[$r->reason] ?? $r->reason),
                    'details' => $r->details,
                    'reporter' => $r->reporter?->name,
                    'author' => $author ? ['id' => $author->id, 'name' => $author->name] : null,
                    'church' => $r->church?->name,
                    'status' => $r->status,
                    'at' => $r->created_at?->toIso8601String(),
                ];
            })->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** hide (the content is hidden), dismiss (it is fine), or suspend (the author's account is suspended and the content hidden; asks for the password). */
    public function handleReport(Request $request, ContentReport $contentReport): JsonResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(['hide', 'dismiss', 'suspend'])]])['action'];
        abort_unless($contentReport->status === 'open', 422, __('This report has been handled already.'));

        if ($action === 'suspend') {
            $this->confirm($request);
            $author = $this->moderation->author($contentReport->reportable);
            abort_unless($author && ! $author->isSuperAdmin(), 422, __('This person cannot be suspended.'));
            $author->update(['status' => 'suspended']);
            Audit::log('user.suspended', $author, __('Suspended after moderation report #:id', ['id' => $contentReport->id]));
            $this->moderation->hide($contentReport, $request->user());
        } elseif ($action === 'hide') {
            $this->moderation->hide($contentReport, $request->user());
        } else {
            $this->moderation->dismiss($contentReport, $request->user());
        }

        return response()->json(['message' => match ($action) {
            'hide' => __('The content is hidden.'),
            'dismiss' => __('The report was dismissed.'),
            default => __('The person was suspended and the content hidden.'),
        }]);
    }

    public function podcasts(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'hidden'])]]);
        $page = Podcast::with('owner')->withCount('episodes')->where('status', $request->query('status', 'pending'))->latest()->paginate(20)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (Podcast $p) => [
                'id' => $p->id, 'name' => $p->name, 'host' => $p->host_name, 'summary' => Str::limit((string) $p->summary, 280), 'category' => $p->category,
                'status' => $p->status, 'review_note' => $p->review_note, 'episodes' => $p->episodes_count, 'cover_url' => $p->cover_url, 'owner' => $p->owner?->name,
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function reviewPodcast(Request $request, Podcast $podcast): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject', 'hide'])], 'reason' => ['nullable', 'string', 'max:500']]);
        if ($data['action'] === 'reject' && empty($data['reason'])) {
            throw ValidationException::withMessages(['reason' => __('Say why, so the podcaster knows what to change.')]);
        }

        match ($data['action']) {
            'approve' => $this->podcasts->approve($podcast),
            'reject' => $this->podcasts->reject($podcast, $data['reason']),
            default => $this->podcasts->hide($podcast, $data['reason'] ?? null),
        };

        return response()->json(['message' => match ($data['action']) {
            'approve' => __('“:name” is approved and now in the app.', ['name' => $podcast->name]),
            'reject' => __('The podcaster was told why it was not approved.'),
            default => __('The podcast is hidden from the app.'),
        }]);
    }

    // ── What goes out to everyone ───────────────────────────────────────────────────────────────

    /** A notification to every phone that has the app (asks for the password). */
    public function announce(Request $request, PushBroadcaster $push): JsonResponse
    {
        $this->confirm($request);
        $data = $request->validate(['title' => ['required', 'string', 'max:80'], 'body' => ['required', 'string', 'max:240']]);

        $sent = $push->toEveryone('announcement', ['title' => $data['title'], 'body' => $data['body'], 'data' => ['kind' => 'announcement']]);
        Audit::log('platform.announced', null, __('Notification to everyone: :title', ['title' => $data['title']]), [], ['title' => $data['title'], 'body' => $data['body'], 'sent' => $sent]);

        return response()->json(['sent' => $sent, 'message' => __('Sent to :n people.', ['n' => $sent])]);
    }

    /** The platform's songs and teaching that can be put on every radio at once, and what is on now. */
    public function radio(): JsonResponse
    {
        $broadcast = $this->radio->activeBroadcast();
        $stations = [RadioStation::gospel(), ...(RadioStation::ready() ? [RadioStation::podcast()] : [])];

        return response()->json(['data' => [
            'broadcast' => $broadcast ? ['title' => $broadcast['track']->title, 'ends_at' => date(DATE_ATOM, $broadcast['ends']), 'message' => $broadcast['message']] : null,
            'tracks' => collect($stations)->flatMap(fn (RadioStation $s) => $this->radio->tracks($s)->map(fn (RadioTrack $t) => [
                'id' => $t->id, 'title' => $t->title, 'artist' => $t->artist, 'duration' => (int) $t->duration_seconds, 'channel' => $this->radio->name($s),
            ]))->values(),
        ]]);
    }

    public function startBroadcast(Request $request): JsonResponse
    {
        $this->confirm($request);
        $data = $request->validate(['track' => ['required', 'integer'], 'message' => ['nullable', 'string', 'max:140'], 'notify' => ['boolean']]);
        $track = RadioTrack::findOrFail($data['track']);
        abort_unless($track->channel()->isPlatform() && $track->duration_seconds > 0, 404);

        $this->radio->startBroadcast($track, $data['message'] ?? null, $request->user(), $request->boolean('notify'));
        Audit::log('radio.broadcast', null, __('Broadcast to everyone: :title', ['title' => $track->title]), [], ['track' => $track->id, 'message' => $data['message'] ?? null, 'notify' => $request->boolean('notify')]);

        return response()->json(['message' => __('“:title” is playing for everyone now.', ['title' => $track->title])]);
    }

    public function stopBroadcast(Request $request): JsonResponse
    {
        $this->confirm($request);
        $this->radio->stopBroadcast();
        Audit::log('radio.broadcast_stopped', null, __('Broadcast to everyone stopped'));

        return response()->json(['message' => __('The broadcast was stopped. Every channel is back to what it had on.')]);
    }
}
