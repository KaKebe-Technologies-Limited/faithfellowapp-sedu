<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Church;
use App\Models\Donation;
use App\Models\User;
use App\Notifications\Giving\CampaignMilestoneReached;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Campaign progress, milestones (25/50/75/100%), goal celebration and leaderboards.
 */
class CampaignService
{
    public const DEFAULT_MILESTONES = [25, 50, 75, 100];

    public function __construct(protected NotificationService $notifications) {}

    public function createDefaultMilestones(Campaign $campaign): void
    {
        foreach (self::DEFAULT_MILESTONES as $percent) {
            $campaign->milestones()->firstOrCreate(['percent' => $percent], [
                'church_id' => $campaign->church_id,
                'title' => $percent === 100 ? __('Goal reached!') : __(':p% funded', ['p' => $percent]),
            ]);
        }
    }

    /**
     * Re-counts the campaigns of a church whose saved total is less than its confirmed gifts add up to. The total is a saved figure that a job keeps
     * up to date; if the job did not run, the next look at the campaign brings it right. (Only a total that fell behind is corrected, never one that is
     * higher, so figures entered some other way, such as the demonstration data, are left alone.) Returns how many were put right.
     */
    public function healChurch(Church $church): int
    {
        $stale = $church->campaigns()->whereRaw('ROUND(raised_amount) < ROUND((select coalesce(sum(amount), 0) from donations where donations.campaign_id = campaigns.id and donations.status = ?))', ['succeeded'])->get();
        $stale->each(fn (Campaign $c) => $this->recalculate($c));

        return $stale->count();
    }

    public function healCampaign(Campaign $campaign): void
    {
        $sum = (float) Donation::where('campaign_id', $campaign->id)->where('status', 'succeeded')->sum('amount');
        if (round((float) $campaign->raised_amount) < round($sum)) {
            $this->recalculate($campaign);
            $campaign->refresh();
        }
    }

    /**
     * Recompute totals from confirmed gifts and trigger milestone celebrations.
     */
    public function recalculate(Campaign $campaign): Campaign
    {
        $newlyReached = DB::transaction(function () use ($campaign) {
            $campaign = Campaign::whereKey($campaign->id)->lockForUpdate()->first();
            $succeeded = Donation::where('campaign_id', $campaign->id)->where('status', 'succeeded');

            $campaign->raised_amount = (clone $succeeded)->sum('amount');
            $campaign->givers_count = (clone $succeeded)->select(DB::raw('COUNT(DISTINCT COALESCE(user_id, -id)) as c'))->value('c') ?? 0;
            $campaign->pledged_amount = $campaign->pledges()->whereIn('status', ['active', 'fulfilled', 'overdue'])->sum('amount');
            $campaign->save();

            foreach ($campaign->teams as $team) {
                $team->update(['raised_amount' => (clone $succeeded)->where('campaign_team_id', $team->id)->sum('amount')]);
            }

            $percent = $campaign->percent();
            $reached = $campaign->milestones()->whereNull('reached_at')->where('percent', '<=', $percent)->orderBy('percent')->get();
            foreach ($reached as $milestone) {
                $milestone->update(['reached_at' => now()]);
            }

            if ($percent >= 100 && ! $campaign->goal_reached_at) {
                $campaign->update(['goal_reached_at' => now()]);
                $campaign->updates()->create([
                    'church_id' => $campaign->church_id,
                    'title' => __('🎉 We reached our goal!'),
                    'body' => '<p>'.e(__('Thank you to all :n givers who made this possible. To God be the glory!', ['n' => $campaign->givers_count])).'</p>',
                    'published_at' => now(),
                ]);
            }

            return $reached->max('percent');
        });

        $campaign->refresh()->load('church');

        if ($newlyReached) {
            $this->celebrate($campaign, (int) $newlyReached);
        }

        return $campaign;
    }

    /**
     * Notify subscribers of milestones; at 100% also thank every giver personally.
     */
    protected function celebrate(Campaign $campaign, int $percent): void
    {
        $this->notifications->notifyChurch($campaign->church, new CampaignMilestoneReached($campaign, $percent));

        if ($percent >= 100) {
            $giverIds = Donation::where('campaign_id', $campaign->id)->where('status', 'succeeded')->whereNotNull('user_id')->distinct()->pluck('user_id');
            $notSubscribed = User::whereIn('id', $giverIds)
                ->whereDoesntHave('subscriptions', fn ($q) => $q->where('church_id', $campaign->church_id)->where('status', 'active'))
                ->get();
            Notification::send($notSubscribed, new CampaignMilestoneReached($campaign, 100));
        }
    }

    /**
     * Top givers who opted in (never anonymous gifts).
     *
     * @return Collection<int, object{name: string, total: float, user_id: int}>
     */
    public function leaderboard(Campaign $campaign, int $limit = 10): Collection
    {
        if (! $campaign->show_leaderboard) {
            return collect();
        }

        return Donation::query()
            ->join('users', 'users.id', '=', 'donations.user_id')
            ->where('donations.campaign_id', $campaign->id)
            ->where('donations.status', 'succeeded')
            ->where('donations.is_anonymous', false)
            ->where('donations.show_on_leaderboard', true)
            ->groupBy('donations.user_id', 'users.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get([DB::raw('donations.user_id as user_id'), 'users.name', DB::raw('SUM(donations.amount) as total')]);
    }
}
