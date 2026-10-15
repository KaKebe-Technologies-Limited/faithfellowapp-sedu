<?php

namespace App\Services;

use App\Models\Church;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The people who follow a church, in full: how many, how fast the church is growing, who they are (stage, gender, age, town), how active they
 * are in the app and how many give. Used by the church's own admin pages and by the super admin. Counts only; contact details stay on the members page.
 */
class SubscriberStats
{
    /**
     * @return array<string, mixed>
     */
    public function forChurch(Church $church): array
    {
        $followers = fn () => DB::table('subscriptions')->where('church_id', $church->id)->where('status', 'active');
        $active = (int) $followers()->count();
        $now = now();

        $joined = fn (int $from, int $to) => (int) $followers()->whereBetween('subscribed_at', [$now->copy()->subDays($from), $now->copy()->subDays($to)])->count();
        $last30 = $joined(30, 0);
        $before30 = $joined(60, 30);

        $ids = fn () => $followers()->select('user_id');
        $users = fn () => DB::table('users')->whereIn('users.id', $ids());

        // Who opened the app lately: by the app's own "opened today" count, with the last sign-in as a fallback for people who use the website.
        $openedSince = fn (int $days) => (int) DB::table('users')->whereIn('users.id', $ids())->where(fn ($q) => $q
            ->where('users.last_login_at', '>=', $now->copy()->subDays($days))
            ->orWhereExists(fn ($e) => $e->select(DB::raw(1))->from('member_activity')->whereColumn('member_activity.user_id', 'users.id')->where('member_activity.day', '>=', $now->copy()->subDays($days)->toDateString())))->count();

        $activity = DB::table('member_activity')->whereIn('user_id', $ids())->where('day', '>=', $now->copy()->subDays(30)->toDateString())
            ->selectRaw('kind, SUM(seconds) as seconds, SUM(count) as count')->groupBy('kind')->get()->keyBy('kind');
        $gave = (int) DB::table('donations')->where('church_id', $church->id)->where('status', 'succeeded')->whereNotNull('user_id')->where('paid_at', '>=', $now->copy()->subDays(90))->distinct()->count('user_id');

        return [
            'church' => ['id' => $church->id, 'name' => $church->name, 'slug' => $church->slug],
            'totals' => [
                'followers' => $active,
                'pending' => (int) DB::table('subscriptions')->where('church_id', $church->id)->where('status', 'pending')->count(),
                'left' => (int) DB::table('subscriptions')->where('church_id', $church->id)->where('status', 'left')->count(),
                'new_7' => $joined(7, 0),
                'new_30' => $last30,
                'new_prev_30' => $before30,
                'growth' => $before30 > 0 ? (int) round(($last30 - $before30) / $before30 * 100) : null,
            ],
            'activity' => [
                'opened_7' => $openedSince(7),
                'opened_30' => $openedSince(30),
                'opened_30_percent' => $active ? (int) round($openedSince(30) / $active * 100) : 0,
                'read_minutes_30' => intdiv((int) ($activity['read']->seconds ?? 0), 60),
                'listen_minutes_30' => intdiv((int) (($activity['radio']->seconds ?? 0) + ($activity['podcast']->seconds ?? 0)), 60),
                'shares_30' => (int) (($activity['share_scripture']->count ?? 0) + ($activity['share_inspiration']->count ?? 0)),
                'gave_90' => $gave,
                'gave_90_percent' => $active ? (int) round($gave / $active * 100) : 0,
            ],
            'weekly' => $this->weekly($church),
            'stages' => $this->grouped($followers()->selectRaw('stage as label, COUNT(*) as n')->groupBy('stage'), ['visitor' => 'Visitors', 'new_member' => 'New members', 'member' => 'Members', 'worker' => 'Workers', 'leader' => 'Leaders']),
            'sources' => $this->grouped($followers()->whereNotNull('source')->selectRaw('source as label, COUNT(*) as n')->groupBy('source'), ['directory' => 'Church directory', 'qr' => 'QR code', 'invite' => 'Invitation', 'website' => 'Website', 'campaign' => 'Campaign', 'app' => 'The app']),
            'genders' => $this->grouped($users()->whereNotNull('gender')->selectRaw('gender as label, COUNT(*) as n')->groupBy('gender'), ['male' => 'Men', 'female' => 'Women']),
            'ages' => $this->ages($users()->whereNotNull('date_of_birth')->pluck('date_of_birth')),
            'towns' => $users()->whereNotNull('city')->where('city', '!=', '')->selectRaw('city as label, COUNT(*) as n')->groupBy('city')->orderByDesc('n')->limit(8)->get()->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->n])->all(),
            'branches' => DB::table('subscriptions')->where('subscriptions.church_id', $church->id)->where('subscriptions.status', 'active')->join('branches', 'branches.id', '=', 'subscriptions.branch_id')->selectRaw('branches.name as label, COUNT(*) as n')->groupBy('branches.name')->orderByDesc('n')->get()->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->n])->all(),
            'recent' => DB::table('subscriptions')->join('users', 'users.id', '=', 'subscriptions.user_id')->where('subscriptions.church_id', $church->id)->where('subscriptions.status', 'active')
                ->orderByDesc('subscriptions.subscribed_at')->limit(12)->get(['users.id', 'users.name', 'subscriptions.subscribed_at', 'subscriptions.stage', 'users.city'])
                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'joined' => Carbon::parse($r->subscribed_at)->toDateString(), 'stage' => $r->stage, 'city' => $r->city])->all(),
        ];
    }

    /** One line for each church, for the super admin: followers, growth and how active they are. */
    public function overview(): Collection
    {
        $churches = Church::whereIn('status', ['active', 'suspended'])->orderBy('name')->get();
        $counts = DB::table('subscriptions')->where('status', 'active')->selectRaw('church_id, COUNT(*) as followers, SUM(subscribed_at >= ?) as new_30', [now()->subDays(30)])->groupBy('church_id')->get()->keyBy('church_id');

        return $churches->map(fn (Church $c) => [
            'church' => $c,
            'followers' => (int) ($counts[$c->id]->followers ?? 0),
            'new_30' => (int) ($counts[$c->id]->new_30 ?? 0),
        ]);
    }

    /** @return array{type: string, data: array<string, mixed>} new followers each week for twelve weeks */
    protected function weekly(Church $church): array
    {
        $from = now()->subWeeks(11)->startOfWeek();
        $rows = DB::table('subscriptions')->where('church_id', $church->id)->where('subscribed_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(DATE_SUB(subscribed_at, INTERVAL WEEKDAY(subscribed_at) DAY), '%Y-%m-%d') as wk, COUNT(*) as n")->groupBy('wk')->pluck('n', 'wk');

        $labels = [];
        $values = [];
        for ($i = 0; $i < 12; $i++) {
            $week = $from->copy()->addWeeks($i);
            $labels[] = $week->format('j M');
            $values[] = (int) ($rows[$week->format('Y-m-d')] ?? 0);
        }

        return ['type' => 'bar', 'data' => ['labels' => $labels, 'datasets' => [['label' => __('New followers'), 'data' => $values]]]];
    }

    /**
     * @param  array<string, string>  $names
     * @return array<int, array{label: string, count: int}>
     */
    protected function grouped($query, array $names): array
    {
        return $query->get()->map(fn ($r) => ['label' => __($names[$r->label] ?? ucfirst((string) $r->label)), 'count' => (int) $r->n])->sortByDesc('count')->values()->all();
    }

    /** @return array<int, array{label: string, count: int}> */
    protected function ages(Collection $births): array
    {
        $bands = ['Under 18' => 0, '18–24' => 0, '25–34' => 0, '35–44' => 0, '45–54' => 0, '55+' => 0];
        foreach ($births as $born) {
            $age = Carbon::parse($born)->age;
            $key = match (true) {
                $age < 18 => 'Under 18', $age <= 24 => '18–24', $age <= 34 => '25–34', $age <= 44 => '35–44', $age <= 54 => '45–54', default => '55+',
            };
            $bands[$key]++;
        }

        return collect($bands)->map(fn ($n, $label) => ['label' => $label, 'count' => $n])->values()->all();
    }
}
