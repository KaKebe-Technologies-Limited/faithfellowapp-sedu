<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\DiscipleshipRecord;
use App\Models\HomeVisit;
use App\Models\PastoralNote;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WelfareRecord;
use App\Services\CareService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\View\View;

/**
 * Pastoral care dashboard. Everything here is confidential (care.manage) and
 * the free-text fields are encrypted at rest.
 */
class CareController extends Controller
{
    public function index(Church $church, CareService $care): View
    {
        $milestones = $church->discipleshipRecords()->selectRaw('milestone, count(*) c')->groupBy('milestone')->pluck('c', 'milestone');

        return view('church-admin.care.index', [
            'church' => $church,
            'celebrations' => $care->upcomingCelebrations($church, 14),
            'followUps' => $care->followUps($church),
            'visits' => $church->homeVisits()->with(['member.media', 'visitor'])->where('status', 'scheduled')->orderBy('scheduled_at')->take(10)->get(),
            'welfare' => $church->welfareRecords()->with('user')->latest('given_on')->take(8)->get(),
            'welfareTotal' => $church->welfareRecords()->where('given_on', '>=', now()->startOfYear())->sum('amount'),
            'counseling' => $church->counselingAppointments()->with('member.media')->whereIn('status', ['requested', 'confirmed'])->orderByRaw('scheduled_at IS NULL, scheduled_at')->take(8)->get(),
            'milestones' => collect(DiscipleshipRecord::MILESTONES)->map(fn ($label, $key) => ['label' => __($label), 'count' => (int) ($milestones[$key] ?? 0)]),
            'people' => $this->people($church),
        ]);
    }

    /**
     * One member's care record.
     */
    public function member(Request $request, Church $church, int $memberId): View
    {
        $subscription = Subscription::where('church_id', $church->id)->where('user_id', $memberId)->firstOrFail();
        $user = User::findOrFail($memberId);

        return view('church-admin.care.member', [
            'church' => $church,
            'member' => $user->load('media'),
            'subscription' => $subscription,
            'notes' => $church->pastoralNotes()->with('author')->where('user_id', $user->id)->readableBy($request->user())->latest()->get(),
            'welfare' => $church->welfareRecords()->where('user_id', $user->id)->latest('given_on')->get(),
            'visits' => $church->homeVisits()->with('visitor')->where('user_id', $user->id)->latest('scheduled_at')->get(),
            'milestones' => $church->discipleshipRecords()->with('mentor')->where('user_id', $user->id)->get()->keyBy('milestone'),
            'groups' => $church->smallGroups()->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id))->get(),
            'attendance' => $church->attendanceRecords()->where('user_id', $user->id)->where('service_date', '>=', today()->subWeeks(12))->count(),
            'staff' => User::whereIn('id', $church->staffMembers()->where('status', 'active')->select('user_id'))->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function storeNote(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', $this->memberRule($church)],
            'body' => ['required', 'string', 'max:5000'],
            'is_private' => ['boolean'],
        ]);
        $note = $church->pastoralNotes()->create([...$data, 'author_id' => $request->user()->id, 'is_private' => $request->boolean('is_private')]);
        Audit::log('care.note_added', $note);

        return back()->with('success', __('Note saved (encrypted).'));
    }

    public function destroyNote(Request $request, Church $church, PastoralNote $pastoralNote): RedirectResponse
    {
        abort_unless($pastoralNote->author_id === $request->user()->id, 403);
        $pastoralNote->delete();

        return back()->with('success', __('Note deleted.'));
    }

    public function storeWelfare(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'required_without:recipient_name', $this->memberRule($church)],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(WelfareRecord::TYPES))],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'given_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $record = $church->welfareRecords()->create([...$data, 'amount' => $data['amount'] ?? 0, 'recorded_by' => $request->user()->id]);
        Audit::log('care.welfare_recorded', $record);

        return back()->with('success', __('Welfare support recorded.'));
    }

    public function storeVisit(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', $this->memberRule($church)],
            'visitor_id' => ['nullable', Rule::exists('church_staff', 'user_id')->where('church_id', $church->id)],
            'scheduled_at' => ['required', 'date'],
            'purpose' => ['nullable', 'string', 'max:200'],
        ]);
        $church->homeVisits()->create([...$data, 'visitor_id' => $data['visitor_id'] ?? $request->user()->id, 'status' => 'scheduled']);

        return back()->with('success', __('Home visit scheduled.'));
    }

    public function updateVisit(Request $request, Church $church, HomeVisit $homeVisit): RedirectResponse
    {
        $homeVisit->update($request->validate([
            'status' => ['required', Rule::in(['scheduled', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with('success', __('Visit updated.'));
    }

    public function storeMilestone(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', $this->memberRule($church)],
            'milestone' => ['required', Rule::in(array_keys(DiscipleshipRecord::MILESTONES))],
            'completed_on' => ['required', 'date', 'before_or_equal:today'],
            'mentor_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $church->discipleshipRecords()->updateOrCreate(
            ['user_id' => $data['user_id'], 'milestone' => $data['milestone']],
            [...$data, 'church_id' => $church->id],
        );

        return back()->with('success', __('Milestone recorded.'));
    }

    public function destroyMilestone(Church $church, DiscipleshipRecord $discipleshipRecord): RedirectResponse
    {
        $discipleshipRecord->delete();

        return back()->with('success', __('Milestone removed.'));
    }

    protected function memberRule(Church $church): Exists
    {
        return Rule::exists('subscriptions', 'user_id')->where('church_id', $church->id);
    }

    protected function people(Church $church)
    {
        return User::whereIn('id', $church->subscriptions()->where('status', 'active')->select('user_id'))->orderBy('name')->limit(1000)->pluck('name', 'id');
    }
}
