<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\SmallGroup;
use App\Models\SmallGroupAttendance;
use App\Models\SmallGroupMeeting;
use App\Models\SmallGroupMember;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Care\CommunityUpdate;
use App\Services\ContentPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $memberIds = $user->memberChurchIds();

        $mine = SmallGroup::with(['church.media', 'leader'])->withCount('members')
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id))->get();

        $discover = SmallGroup::with(['church.media', 'leader'])->withCount('members')
            ->whereIn('church_id', $memberIds ?: [0])->where('status', 'active')->where('is_open', true)
            ->whereNotIn('id', $mine->pluck('id'))->orderBy('name')->get();

        return view('member.groups.index', [
            'mine' => $mine,
            'discover' => $discover,
            'pending' => SmallGroupMember::where('user_id', $user->id)->where('status', 'pending')->pluck('small_group_id')->all(),
            'mapPoints' => $discover->merge($mine)->filter(fn ($g) => $g->lat && $g->lng)
                ->map(fn ($g) => ['lat' => $g->lat, 'lng' => $g->lng, 'label' => $g->name, 'sub' => $g->schedule, 'url' => route('groups.show', $g)])->values(),
        ]);
    }

    public function show(Request $request, SmallGroup $group): View
    {
        $this->authorize('view', $group);
        $group->load(['church.media', 'leader.media']);
        $canLead = $request->user()->can('lead', $group);
        $memberships = $group->memberships()->with('user.media')->orderByRaw("FIELD(role, 'leader', 'assistant', 'member')")->get();
        $isMember = $memberships->contains(fn ($m) => $m->user_id === $request->user()->id && $m->status === 'active');

        return view('member.groups.show', [
            'group' => $group,
            'membership' => $memberships->firstWhere('user_id', $request->user()->id),
            'memberships' => $memberships->where('status', 'active'),
            'pendingMembers' => $canLead ? $memberships->where('status', 'pending') : collect(),
            'canLead' => $canLead,
            'candidates' => $canLead
                ? User::whereIn('id', Subscription::where('church_id', $group->church_id)->where('status', 'active')->select('user_id'))
                    ->whereNotIn('id', $memberships->pluck('user_id'))->orderBy('name')->limit(500)->pluck('name', 'id')
                : collect(),
            'announcements' => $isMember || $canLead
                ? Announcement::where('small_group_id', $group->id)->current()->latest('published_at')->take(10)->get()
                : collect(),
            'meetings' => $isMember || $canLead
                ? $group->meetings()->withCount(['attendance as present_count' => fn ($q) => $q->where('status', 'present')])->take(8)->get()
                : collect(),
        ]);
    }

    public function join(Request $request, SmallGroup $group): RedirectResponse
    {
        $this->authorize('view', $group);
        abort_unless($group->is_open && $group->status === 'active', 422, __('This group is not accepting new members.'));

        SmallGroupMember::firstOrCreate(
            ['small_group_id' => $group->id, 'user_id' => $request->user()->id],
            ['church_id' => $group->church_id, 'status' => 'pending', 'role' => 'member'],
        );

        $group->leader?->notify(new CommunityUpdate($group->church, __(':name asked to join :group', ['name' => $request->user()->name, 'group' => $group->name]), route('groups.show', $group), null, 'bi-people'));

        return back()->with('success', __('Request sent to the group leader.'));
    }

    public function leave(Request $request, SmallGroup $group): RedirectResponse
    {
        SmallGroupMember::where('small_group_id', $group->id)->where('user_id', $request->user()->id)->delete();

        return redirect()->route('groups.index')->with('success', __('You left :group.', ['group' => $group->name]));
    }

    /*
    |--------------------------------------------------------------------------
    | Leader tools (group leaders, assistants and staff with groups.manage)
    |--------------------------------------------------------------------------
    */

    public function addMember(Request $request, SmallGroup $group): RedirectResponse
    {
        $this->authorize('lead', $group);
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('subscriptions', 'user_id')->where('church_id', $group->church_id)->where('status', 'active')],
            'role' => ['nullable', Rule::in(['member', 'assistant', 'leader'])],
        ]);

        SmallGroupMember::updateOrCreate(
            ['small_group_id' => $group->id, 'user_id' => $data['user_id']],
            ['church_id' => $group->church_id, 'status' => 'active', 'role' => $data['role'] ?? 'member', 'joined_at' => now()],
        );

        return back()->with('success', __('Member added.'));
    }

    public function updateMember(Request $request, SmallGroup $group, SmallGroupMember $membership): RedirectResponse
    {
        $this->authorize('lead', $group);
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'remove', 'member', 'assistant', 'leader'])]]);

        match ($data['action']) {
            'remove' => $membership->delete(),
            'approve' => $membership->update(['status' => 'active', 'joined_at' => now()]),
            default => $membership->update(['role' => $data['action']]),
        };

        if ($data['action'] === 'approve') {
            $membership->user?->notify(new CommunityUpdate($group->church, __('Welcome to :group!', ['group' => $group->name]), route('groups.show', $group), $group->schedule, 'bi-people'));
        }

        return back()->with('success', __('Membership updated.'));
    }

    /**
     * Record a meeting with attendance; members not ticked are marked absent.
     */
    public function storeMeeting(Request $request, SmallGroup $group): RedirectResponse
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

        DB::transaction(function () use ($group, $data, $request) {
            $meeting = SmallGroupMeeting::updateOrCreate(
                ['small_group_id' => $group->id, 'meeting_date' => $data['meeting_date']],
                [
                    'church_id' => $group->church_id, 'topic' => $data['topic'] ?? null, 'visitors' => $data['visitors'] ?? 0,
                    'offering_amount' => $data['offering_amount'] ?? 0, 'notes' => $data['notes'] ?? null, 'recorded_by' => $request->user()->id,
                ],
            );

            $present = array_map('intval', $data['present'] ?? []);
            foreach ($group->memberships()->where('status', 'active')->pluck('user_id') as $userId) {
                SmallGroupAttendance::updateOrCreate(
                    ['small_group_meeting_id' => $meeting->id, 'user_id' => $userId],
                    ['church_id' => $group->church_id, 'small_group_id' => $group->id, 'status' => in_array($userId, $present, true) ? 'present' : 'absent'],
                );
            }
        });

        return back()->with('success', __('Meeting recorded.'));
    }

    public function storeAnnouncement(Request $request, SmallGroup $group, ContentPublisher $publisher): RedirectResponse
    {
        $this->authorize('lead', $group);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:3000']]);

        $announcement = Announcement::create([
            'church_id' => $group->church_id, 'small_group_id' => $group->id, 'user_id' => $request->user()->id,
            'title' => $data['title'], 'body' => nl2br(e($data['body'])), 'audience' => 'group', 'priority' => 'normal',
            'status' => 'published', 'published_at' => now(), 'channels' => [],
        ]);
        $publisher->announceIfLive($announcement);

        return back()->with('success', __('Announcement sent to the group.'));
    }
}
