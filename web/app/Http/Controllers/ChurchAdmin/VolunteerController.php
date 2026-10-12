<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\User;
use App\Models\VolunteerRota;
use App\Models\VolunteerTeam;
use App\Notifications\Care\CommunityUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\View\View;

/**
 * Volunteer teams (ushers, choir, media, children's church…) and the serving rota.
 */
class VolunteerController extends Controller
{
    public function index(Request $request, Church $church): View
    {
        $from = Carbon::parse($request->query('from', now()->startOfWeek(Carbon::SUNDAY)->toDateString()))->startOfDay();
        $dates = collect(range(0, 4))->map(fn ($w) => $from->copy()->addWeeks($w));

        $rotas = $church->volunteerRotas()->with('user.media')
            ->whereBetween('service_date', [$from->toDateString(), $dates->last()->toDateString()])
            ->orderBy('service_date')->get()
            ->groupBy(fn ($r) => $r->volunteer_team_id.'|'.$r->service_date->toDateString());

        return view('church-admin.volunteers.index', [
            'church' => $church,
            'teams' => $church->volunteerTeams()->with(['leader', 'members.media'])->withCount('members')->orderBy('name')->get(),
            'dates' => $dates,
            'rotas' => $rotas,
            'from' => $from,
            'people' => $this->people($church),
            'declined' => $church->volunteerRotas()->with(['user', 'team'])->where('status', 'declined')->whereDate('service_date', '>=', today())->get(),
        ]);
    }

    public function storeTeam(Request $request, Church $church): RedirectResponse
    {
        $church->volunteerTeams()->create($this->teamData($request, $church));

        return back()->with('success', __('Team created.'));
    }

    public function updateTeam(Request $request, Church $church, VolunteerTeam $volunteerTeam): RedirectResponse
    {
        $volunteerTeam->update($this->teamData($request, $church));

        return back()->with('success', __('Team updated.'));
    }

    public function destroyTeam(Church $church, VolunteerTeam $volunteerTeam): RedirectResponse
    {
        $volunteerTeam->delete();

        return back()->with('success', __('Team removed.'));
    }

    public function addMember(Request $request, Church $church, VolunteerTeam $volunteerTeam): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', $this->subscriberRule($church)]]);
        $volunteerTeam->members()->syncWithoutDetaching([$data['user_id'] => ['church_id' => $church->id]]);

        return back()->with('success', __('Volunteer added to :team.', ['team' => $volunteerTeam->name]));
    }

    public function removeMember(Church $church, VolunteerTeam $volunteerTeam, int $userId): RedirectResponse
    {
        $volunteerTeam->members()->detach($userId);

        return back()->with('success', __('Volunteer removed.'));
    }

    /**
     * Schedule a volunteer for a service date and let them know.
     */
    public function storeRota(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'volunteer_team_id' => ['required', Rule::exists('volunteer_teams', 'id')->where('church_id', $church->id)],
            'user_id' => ['required', $this->subscriberRule($church)],
            'service_date' => ['required', 'date', 'after_or_equal:today'],
            'role' => ['nullable', 'string', 'max:80'],
        ]);

        $rota = $church->volunteerRotas()->create([...$data, 'status' => 'scheduled']);
        $rota->load('team', 'user');
        $rota->user->notify(new CommunityUpdate(
            $church,
            __('You are serving with :team on :date', ['team' => $rota->team->name, 'date' => $rota->service_date->format('D j M')]),
            route('volunteering.index'),
            __('Please confirm or decline so your team leader can plan.'),
            'bi-hand-thumbs-up',
            'event',
        ));

        return back()->with('success', __('Scheduled. :name has been notified.', ['name' => $rota->user->name]));
    }

    public function destroyRota(Church $church, VolunteerRota $volunteerRota): RedirectResponse
    {
        $volunteerRota->delete();

        return back()->with('success', __('Removed from the rota.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function teamData(Request $request, Church $church): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'leader_id' => ['nullable', $this->subscriberRule($church)],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
    }

    protected function subscriberRule(Church $church): Exists
    {
        return Rule::exists('subscriptions', 'user_id')->where('church_id', $church->id)->where('status', 'active');
    }

    protected function people(Church $church)
    {
        return User::whereIn('id', $church->subscriptions()->where('status', 'active')->select('user_id'))->orderBy('name')->limit(1000)->pluck('name', 'id');
    }
}
