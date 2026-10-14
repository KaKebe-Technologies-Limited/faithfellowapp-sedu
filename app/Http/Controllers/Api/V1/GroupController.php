<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChurchSummaryResource;
use App\Models\Announcement;
use App\Models\SmallGroup;
use App\Models\SmallGroupAttendance;
use App\Models\SmallGroupMeeting;
use App\Models\SmallGroupMember;
use App\Models\SmallGroupPost;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Care\CommunityUpdate;
use App\Rules\NoContactDetails;
use App\Services\ContentPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Study groups, cells and fellowships in the app: find and join one, read its notices, meetings and members,
 * talk in its conversation — and, for leaders, approve people, take attendance and post notices.
 */
class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $memberIds = $user->memberChurchIds() ?: [0];
        $membership = SmallGroupMember::where('user_id', $user->id)->get()->keyBy('small_group_id');

        $mine = SmallGroup::with(['church.media', 'leader'])->withCount(['memberships as members_count' => fn ($q) => $q->where('status', 'active')])
            ->whereIn('id', $membership->where('status', 'active')->keys())->get();
        $pending = SmallGroup::with(['church.media', 'leader'])->withCount(['memberships as members_count' => fn ($q) => $q->where('status', 'active')])
            ->whereIn('id', $membership->where('status', 'pending')->keys())->get();
        $discover = SmallGroup::with(['church.media', 'leader'])->withCount(['memberships as members_count' => fn ($q) => $q->where('status', 'active')])
            ->whereIn('church_id', $memberIds)->where('status', 'active')->where('is_open', true)
            ->whereNotIn('id', $membership->keys())
            ->when($request->filled('type') && array_key_exists($request->query('type'), SmallGroup::TYPES), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
            ->orderBy('name')->get();

        $card = fn (SmallGroup $g) => $this->card($g, $membership->get($g->id));

        return response()->json(['data' => [
            'mine' => $mine->map($card)->values(),
            'pending' => $pending->map($card)->values(),
            'discover' => $discover->map($card)->values(),
            'types' => collect(SmallGroup::TYPES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ]]);
    }

    public function show(Request $request, SmallGroup $group): JsonResponse
    {
        $this->authorize('view', $group);
        $user = $request->user();
        $group->load(['church.media', 'leader.media'])->loadCount(['memberships as members_count' => fn ($q) => $q->where('status', 'active')]);
        $canLead = $user->can('lead', $group);
        $memberships = $group->memberships()->with('user.media')->orderByRaw("FIELD(role, 'leader', 'assistant', 'member')")->get();
        $mine = $memberships->firstWhere('user_id', $user->id);
        $isMember = $mine?->status === 'active';

        return response()->json(['data' => $this->card($group, $mine) + [
            'full_description' => $group->description,
            'lat' => $group->lat, 'lng' => $group->lng, 'capacity' => $group->capacity,
            'can_lead' => $canLead,
            'is_member' => $isMember || $canLead,
            'members' => $memberships->where('status', 'active')->map(fn ($m) => ['membership_id' => $m->id, 'user_id' => $m->user_id, 'name' => $m->user?->name, 'avatar_url' => $m->user?->avatar_url, 'role' => $m->role])->values(),
            'pending_members' => $canLead ? $memberships->where('status', 'pending')->map(fn ($m) => ['membership_id' => $m->id, 'user_id' => $m->user_id, 'name' => $m->user?->name, 'avatar_url' => $m->user?->avatar_url])->values() : [],
            'announcements' => $isMember || $canLead
                ? Announcement::where('small_group_id', $group->id)->current()->latest('published_at')->take(10)->get()->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'body' => plain_text($a->body), 'at' => $a->published_at?->toIso8601String()])->values()
                : [],
            'meetings' => $isMember || $canLead
                ? $group->meetings()->withCount(['attendance as present_count' => fn ($q) => $q->where('status', 'present')])->take(8)->get()->map(fn ($m) => ['id' => $m->id, 'date' => $m->meeting_date->toDateString(), 'topic' => $m->topic, 'present' => (int) $m->present_count, 'visitors' => (int) $m->visitors])->values()
                : [],
        ]]);
    }

    public function join(Request $request, SmallGroup $group): JsonResponse
    {
        $this->authorize('view', $group);
        abort_unless($group->is_open && $group->status === 'active', 422, __('This group is not accepting new members.'));

        $membership = SmallGroupMember::firstOrCreate(
            ['small_group_id' => $group->id, 'user_id' => $request->user()->id],
            ['church_id' => $group->church_id, 'status' => 'pending', 'role' => 'member'],
        );
        if ($membership->wasRecentlyCreated) {
            $group->leader?->notify(new CommunityUpdate($group->church, __(':name asked to join :group', ['name' => $request->user()->name, 'group' => $group->name]), route('groups.show', $group), null, 'bi-people'));
        }

        return response()->json(['status' => $membership->status], 201);
    }

    public function leave(Request $request, SmallGroup $group): JsonResponse
    {
        SmallGroupMember::where('small_group_id', $group->id)->where('user_id', $request->user()->id)->delete();

        return response()->json(['left' => true]);
    }

    // ── The group's conversation ───────────────────────────────────────────────────────────────────────

    public function posts(Request $request, SmallGroup $group): JsonResponse
    {
        $this->mayTalk($request, $group);
        $posts = SmallGroupPost::with('user.media')->where('small_group_id', $group->id)
            ->when($request->filled('after'), fn ($q) => $q->where('id', '>', (int) $request->query('after')))
            ->when($request->filled('before'), fn ($q) => $q->where('id', '<', (int) $request->query('before')))
            ->orderByDesc('id')->take(40)->get();

        return response()->json(['data' => $posts->reverse()->values()->map(fn ($p) => $this->post($p, $request->user(), $group))->values(), 'has_more' => $posts->count() === 40]);
    }

    public function storePost(Request $request, SmallGroup $group): JsonResponse
    {
        $this->mayTalk($request, $group);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000', new NoContactDetails]]);
        $post = SmallGroupPost::create(['church_id' => $group->church_id, 'small_group_id' => $group->id, 'user_id' => $request->user()->id, 'body' => trim(strip_tags($data['body']))]);

        return response()->json(['data' => $this->post($post->load('user.media'), $request->user(), $group)], 201);
    }

    public function destroyPost(Request $request, SmallGroup $group, SmallGroupPost $post): JsonResponse
    {
        abort_unless($post->small_group_id === $group->id, 404);
        abort_unless($post->user_id === $request->user()->id || $request->user()->can('lead', $group), 403);
        $post->delete();

        return response()->json(['deleted' => true]);
    }

    // ── Leader tools (group leaders, assistants and staff with groups.manage) ───────────────────────────

    /** Church members who could be added to the group. */
    public function candidates(Request $request, SmallGroup $group): JsonResponse
    {
        $this->authorize('lead', $group);

        return response()->json(['data' => User::whereIn('id', Subscription::where('church_id', $group->church_id)->where('status', 'active')->select('user_id'))
            ->whereNotIn('id', $group->memberships()->select('user_id'))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
            ->orderBy('name')->limit(30)->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()]);
    }

    public function addMember(Request $request, SmallGroup $group): JsonResponse
    {
        $this->authorize('lead', $group);
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('subscriptions', 'user_id')->where('church_id', $group->church_id)->where('status', 'active')],
            'role' => ['nullable', Rule::in(['member', 'assistant', 'leader'])],
        ]);

        $membership = SmallGroupMember::updateOrCreate(
            ['small_group_id' => $group->id, 'user_id' => $data['user_id']],
            ['church_id' => $group->church_id, 'status' => 'active', 'role' => $data['role'] ?? 'member', 'joined_at' => now()],
        );

        return response()->json(['membership_id' => $membership->id], 201);
    }

    public function updateMember(Request $request, SmallGroup $group, SmallGroupMember $membership): JsonResponse
    {
        $this->authorize('lead', $group);
        abort_unless($membership->small_group_id === $group->id, 404);
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'remove', 'member', 'assistant', 'leader'])]]);

        match ($data['action']) {
            'remove' => $membership->delete(),
            'approve' => $membership->update(['status' => 'active', 'joined_at' => now()]),
            default => $membership->update(['role' => $data['action']]),
        };
        if ($data['action'] === 'approve') {
            $membership->user?->notify(new CommunityUpdate($group->church, __('Welcome to :group!', ['group' => $group->name]), route('groups.show', $group), $group->schedule, 'bi-people'));
        }

        return response()->json(['done' => true]);
    }

    /** Record a meeting with attendance; members not in `present` are marked absent. */
    public function storeMeeting(Request $request, SmallGroup $group): JsonResponse
    {
        $this->authorize('lead', $group);
        $data = $request->validate([
            'meeting_date' => ['required', 'date', 'before_or_equal:today'],
            'topic' => ['nullable', 'string', 'max:200'],
            'visitors' => ['nullable', 'integer', 'min:0', 'max:500'],
            'offering_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'present' => ['array'],
            'present.*' => ['integer'],
        ]);

        $meeting = DB::transaction(function () use ($group, $data, $request) {
            $meeting = SmallGroupMeeting::updateOrCreate(
                ['small_group_id' => $group->id, 'meeting_date' => $data['meeting_date']],
                ['church_id' => $group->church_id, 'topic' => $data['topic'] ?? null, 'visitors' => $data['visitors'] ?? 0, 'offering_amount' => $data['offering_amount'] ?? 0, 'notes' => $data['notes'] ?? null, 'recorded_by' => $request->user()->id],
            );
            $present = array_map('intval', $data['present'] ?? []);
            foreach ($group->memberships()->where('status', 'active')->pluck('user_id') as $userId) {
                SmallGroupAttendance::updateOrCreate(
                    ['small_group_meeting_id' => $meeting->id, 'user_id' => $userId],
                    ['church_id' => $group->church_id, 'small_group_id' => $group->id, 'status' => in_array($userId, $present, true) ? 'present' : 'absent'],
                );
            }

            return $meeting;
        });

        return response()->json(['id' => $meeting->id], 201);
    }

    public function storeAnnouncement(Request $request, SmallGroup $group, ContentPublisher $publisher): JsonResponse
    {
        $this->authorize('lead', $group);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:3000', new NoContactDetails]]);

        $announcement = Announcement::create([
            'church_id' => $group->church_id, 'small_group_id' => $group->id, 'user_id' => $request->user()->id,
            'title' => $data['title'], 'body' => nl2br(e($data['body'])), 'audience' => 'group', 'priority' => 'normal',
            'status' => 'published', 'published_at' => now(), 'channels' => [],
        ]);
        $publisher->announceIfLive($announcement);

        return response()->json(['id' => $announcement->id], 201);
    }

    /** Active members and group leaders may talk in the group. */
    protected function mayTalk(Request $request, SmallGroup $group): void
    {
        $this->authorize('view', $group);
        abort_unless($request->user()->can('lead', $group) || $group->memberships()->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 403, __('Join the group to take part in its conversation.'));
    }

    /** @return array<string, mixed> */
    protected function card(SmallGroup $g, ?SmallGroupMember $membership): array
    {
        return [
            'id' => $g->id,
            'name' => $g->name,
            'type' => $g->type,
            'type_label' => SmallGroup::TYPES[$g->type] ?? ucfirst((string) $g->type),
            'description' => Str::limit(plain_text($g->description), 160),
            'schedule' => $g->schedule,
            'location' => $g->location,
            'cover_url' => $g->picture_url,
            'is_open' => (bool) $g->is_open,
            'members_count' => (int) ($g->members_count ?? 0),
            'leader' => $g->leader ? ['id' => $g->leader->id, 'name' => $g->leader->name, 'avatar_url' => $g->leader->avatar_url] : null,
            'church' => new ChurchSummaryResource($g->church),
            'my_status' => $membership?->status,
            'my_role' => $membership?->status === 'active' ? $membership->role : null,
        ];
    }

    /** @return array<string, mixed> */
    protected function post(SmallGroupPost $p, User $viewer, SmallGroup $group): array
    {
        return [
            'id' => $p->id,
            'body' => $p->body,
            'at' => $p->created_at?->toIso8601String(),
            'author' => ['id' => $p->user?->id, 'name' => $p->user?->name, 'avatar_url' => $p->user?->avatar_url],
            'mine' => $p->user_id === $viewer->id,
            'can_delete' => $p->user_id === $viewer->id || $viewer->can('lead', $group),
        ];
    }
}
