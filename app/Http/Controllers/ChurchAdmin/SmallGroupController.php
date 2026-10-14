<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\SmallGroup;
use App\Models\SmallGroupMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Small groups / cells. Day-to-day leader tools (members, meetings, group
 * announcements) live on the group page and are shared with group leaders.
 */
class SmallGroupController extends Controller
{
    public function index(Church $church): View
    {
        $groups = $church->smallGroups()->with(['leader.media', 'branch'])
            ->withCount(['members', 'memberships as pending_count' => fn ($q) => $q->where('status', 'pending')])
            ->orderBy('name')->get();

        // Average attendance over the last 8 weeks, per group.
        $attendance = DB::table('small_group_attendance')->where('church_id', $church->id)
            ->where('created_at', '>=', now()->subWeeks(8))
            ->selectRaw("small_group_id, sum(status = 'present') present, count(*) total")
            ->groupBy('small_group_id')->get()->keyBy('small_group_id');

        return view('church-admin.small-groups.index', [
            'church' => $church,
            'groups' => $groups,
            'attendance' => $attendance,
            'mapPoints' => $groups->filter(fn ($g) => $g->lat && $g->lng)
                ->map(fn ($g) => ['lat' => $g->lat, 'lng' => $g->lng, 'label' => $g->name, 'sub' => $g->schedule, 'url' => route('groups.show', $g)])->values(),
            'cellGiving' => $church->donations()->whereNotNull('small_group_id')->where('status', 'succeeded')
                ->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
        ]);
    }

    public function create(Church $church): View
    {
        return $this->form($church, new SmallGroup(['type' => 'cell', 'is_open' => true, 'status' => 'active']));
    }

    public function store(Request $request, Church $church): RedirectResponse
    {
        $group = $church->smallGroups()->create($this->validated($request, $church));
        $this->syncLeader($group);

        return redirect()->route('groups.show', $group)->with('success', __('Group created. Add members below.'));
    }

    public function edit(Church $church, SmallGroup $smallGroup): View
    {
        return $this->form($church, $smallGroup);
    }

    public function update(Request $request, Church $church, SmallGroup $smallGroup): RedirectResponse
    {
        $smallGroup->update($this->validated($request, $church));
        $this->syncLeader($smallGroup);

        return redirect()->route('church-admin.small-groups.index', $church)->with('success', __('Group updated.'));
    }

    public function destroy(Church $church, SmallGroup $smallGroup): RedirectResponse
    {
        $smallGroup->delete();

        return back()->with('success', __('Group deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, Church $church): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(SmallGroup::TYPES))],
            'description' => ['nullable', 'string', 'max:2000'],
            'leader_id' => ['nullable', Rule::exists('subscriptions', 'user_id')->where('church_id', $church->id)->where('status', 'active')],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('church_id', $church->id)],
            'meeting_day' => ['nullable', 'integer', 'between:0,6'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'capacity' => ['nullable', 'integer', 'min:2', 'max:1000'],
            'is_open' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        return [...$data, 'is_open' => $request->boolean('is_open')];
    }

    /** The leader is always an active member with the leader role. */
    protected function syncLeader(SmallGroup $group): void
    {
        if ($group->leader_id) {
            SmallGroupMember::updateOrCreate(
                ['small_group_id' => $group->id, 'user_id' => $group->leader_id],
                ['church_id' => $group->church_id, 'role' => 'leader', 'status' => 'active', 'joined_at' => now()],
            );
        }
    }

    protected function form(Church $church, SmallGroup $group): View
    {
        return view('church-admin.small-groups.form', [
            'church' => $church,
            'group' => $group,
            'branches' => $church->branches()->orderBy('name')->pluck('name', 'id'),
            'people' => User::whereIn('id', $church->subscriptions()->where('status', 'active')->select('user_id'))->orderBy('name')->limit(1000)->pluck('name', 'id'),
        ]);
    }
}
