<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Sermon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aggregates metrics for church admin and super admin dashboards.
 */
class AnalyticsService
{
    /**
     * Headline numbers for one church.
     *
     * @return array<string, int|float>
     */
    public function churchSummary(Church $church): array
    {
        return [
            'subscribers' => $church->subscriptions()->where('status', 'active')->count(),
            'staff' => $church->staffMembers()->where('status', 'active')->count(),
            'branches' => $church->branches()->count(),
            'service_times' => $church->serviceTimes()->count(),
            'storage_mb' => round((float) \DB::table('media')->where('church_id', $church->id)->sum('size') / 1048576, 1),
        ];
    }

    /**
     * Cards, charts and widgets for the church admin dashboard.
     *
     * @return array{cards: array<int, array<string, mixed>>, charts: array<int, array<string, mixed>>, lists: array<int, array<string, mixed>>, side: array<int, array<string, mixed>>}
     */
    public function churchDashboard(Church $church): array
    {
        $subscribers = $church->subscriptions()->where('status', 'active');
        $newThisMonth = (clone $subscribers)->where('subscribed_at', '>=', now()->subDays(30))->count();
        $newPrevMonth = (clone $subscribers)->whereBetween('subscribed_at', [now()->subDays(60), now()->subDays(30)])->count();

        $cards = [
            [
                'label' => __('Subscribers'), 'value' => number_format($subscribers->count()), 'icon' => 'bi-people', 'primary' => true,
                'trend' => $this->trend($newThisMonth, $newPrevMonth), 'href' => route('church-admin.members.index', $church),
            ],
            [
                'label' => __('New this month'), 'value' => number_format($newThisMonth), 'icon' => 'bi-person-plus',
                'note' => __(':n awaiting approval', ['n' => $church->subscriptions()->where('status', 'pending')->count()]),
            ],
        ];

        if (auth()->user()?->canInChurch($church, 'giving.view')) {
            $finance = $this->financeDashboard($church);
            $cards[] = ['label' => __('Giving this month'), 'value' => ff_money($finance['month']), 'icon' => 'bi-cash-coin', 'trend' => $finance['month_trend'], 'href' => route('church-admin.giving.index', $church)];
            $active = $church->campaigns()->active()->orderByDesc('raised_amount')->first();
            $cards[] = $active
                ? ['label' => __('Top campaign'), 'value' => round($active->percent()).'%', 'icon' => 'bi-flag', 'note' => Str::limit($active->title, 28), 'href' => route('church-admin.campaigns.show', [$church, $active])]
                : ['label' => __('Givers this month'), 'value' => number_format($finance['givers_month']), 'icon' => 'bi-heart'];
        }

        return [
            'cards' => $cards,
            'charts' => [[
                'title' => __('Subscriber growth'), 'subtitle' => __('Last 12 weeks'), 'col' => 'col-lg-12', 'height' => 240,
                'config' => $this->weeklySeries($church->subscriptions()->getQuery(), 'subscribed_at', __('New subscribers')),
            ]],
            'lists' => [],
            'side' => [],
        ];
    }

    /**
     * Finance dashboard: totals, categories, methods, weekly trend.
     *
     * @return array<string, mixed>
     */
    public function financeDashboard(Church $church): array
    {
        $ok = fn () => $church->donations()->getQuery()->where('status', 'succeeded');

        $thisMonth = (float) $ok()->where('paid_at', '>=', now()->startOfMonth())->sum('amount');
        $lastMonth = (float) $ok()->whereBetween('paid_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])->sum('amount');

        $byCategory = $ok()->where('paid_at', '>=', now()->startOfYear())->selectRaw('category, SUM(amount) total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category');
        $byMethod = $ok()->where('paid_at', '>=', now()->startOfYear())->selectRaw('method, SUM(amount) total')->groupBy('method')->orderByDesc('total')->pluck('total', 'method');

        return [
            'today' => (float) $ok()->where('paid_at', '>=', today())->sum('amount'),
            'week' => (float) $ok()->where('paid_at', '>=', now()->startOfWeek())->sum('amount'),
            'month' => $thisMonth,
            'month_trend' => $this->trend($thisMonth, $lastMonth),
            'year' => (float) $ok()->where('paid_at', '>=', now()->startOfYear())->sum('amount'),
            'givers_month' => $ok()->where('paid_at', '>=', now()->startOfMonth())->distinct()->count('user_id'),
            'pending' => $church->donations()->whereIn('status', ['pending', 'processing'])->count(),
            'pledged_outstanding' => (float) $church->pledges()->whereIn('status', ['active', 'overdue'])->sum(\DB::raw('amount - paid_amount')),
            'recurring_monthly' => (float) $church->recurringGifts()->where('status', 'active')
                ->sum(\DB::raw("CASE frequency WHEN 'weekly' THEN amount * 4.33 WHEN 'quarterly' THEN amount / 3 ELSE amount END")),
            'trendChart' => $this->weeklySeries($ok(), 'paid_at', __('Giving (UGX)'), 12, 'amount'),
            'categoryChart' => [
                'type' => 'doughnut',
                'data' => [
                    'labels' => $byCategory->keys()->map(fn ($c) => config('faithfellows.giving_categories.'.$c, $c))->values(),
                    'datasets' => [['data' => $byCategory->values()->map(fn ($v) => (float) $v)]],
                ],
                'legend' => true,
            ],
            'byMethod' => $byMethod,
        ];
    }

    /**
     * Church analytics page: growth, engagement, attendance, prayer, giving and
     * notification delivery over the last $days days, with the previous period
     * as the baseline for trends.
     *
     * @return array<string, mixed>
     */
    public function churchAnalytics(Church $church, int $days = 90, bool $withGiving = false): array
    {
        $from = now()->subDays($days)->startOfDay();
        $prevFrom = $from->copy()->subDays($days);
        $weeks = max(4, min(52, intdiv($days, 7)));
        $id = $church->id;
        $between = fn ($q, string $col, bool $previous = false) => $previous ? $q->whereBetween($col, [$prevFrom, $from]) : $q->where($col, '>=', $from);

        $subs = fn () => DB::table('subscriptions')->where('church_id', $id);
        $views = fn () => DB::table('content_views')->where('church_id', $id);
        $attendance = fn () => DB::table('attendance_records')->where('church_id', $id);
        $prayers = fn () => DB::table('prayer_requests')->where('church_id', $id)->whereNull('deleted_at');
        $streams = fn () => DB::table('live_streams')->where('church_id', $id)->whereNull('deleted_at');

        $newMembers = $between($subs(), 'subscribed_at')->count();
        $viewCount = $between($views(), 'created_at')->count();
        $attendanceTotal = (int) $between($attendance(), 'service_date')->sum('headcount');

        $headline = [
            'members' => ['value' => $subs()->where('status', 'active')->count(), 'trend' => $this->trend($newMembers, $between($subs(), 'subscribed_at', true)->count()), 'note' => __(':n new', ['n' => number_format($newMembers)])],
            'engagement' => ['value' => $viewCount, 'trend' => $this->trend($viewCount, $between($views(), 'created_at', true)->count())],
            'attendance' => ['value' => (int) round($attendanceTotal / max(1, $weeks)), 'trend' => $this->trend($attendanceTotal, (int) $between($attendance(), 'service_date', true)->sum('headcount'))],
            'live' => ['value' => (int) $between($streams(), 'started_at')->sum('peak_viewers'), 'note' => trans_choice(':count service|:count services', $between($streams(), 'started_at')->count())],
            'prayer' => ['value' => $between($prayers(), 'created_at')->count(), 'note' => __(':n prayers offered', ['n' => number_format(DB::table('prayer_interactions')->where('church_id', $id)->where('type', 'prayed')->where('created_at', '>=', $from)->count())])],
        ];

        $charts = [
            'growth' => $this->weeklySeries($subs(), 'subscribed_at', __('New members'), $weeks),
            'engagement' => $this->mergeSeries([
                $this->weeklySeries($views()->where('action', 'view'), 'created_at', __('Views'), $weeks),
                $this->weeklySeries($views()->whereIn('action', ['listen', 'watch']), 'created_at', __('Listens & watches'), $weeks),
                $this->weeklySeries($views()->where('action', 'download'), 'created_at', __('Downloads'), $weeks),
            ]),
            'attendance' => ['type' => 'bar', ...array_diff_key($this->weeklySeries($attendance(), 'service_date', __('Attendance'), $weeks, 'headcount'), ['type' => 1])],
            'stages' => $this->doughnut($subs()->where('status', 'active')->selectRaw('stage k, COUNT(*) v')->groupBy('stage')->pluck('v', 'k')
                ->mapWithKeys(fn ($v, $k) => [__(Str::headline((string) $k)) => $v])),
            'channels' => $this->doughnut(DB::table('notification_deliveries')->where('church_id', $id)->where('created_at', '>=', $from)->where('status', 'sent')
                ->selectRaw('channel k, COUNT(*) v')->groupBy('channel')->pluck('v', 'k')
                ->mapWithKeys(fn ($v, $k) => [__(config('faithfellows.notification_channels.'.$k, $k)) => $v])),
        ];

        $topSermons = DB::table('content_views')->join('sermons', 'sermons.id', '=', 'content_views.viewable_id')
            ->where('content_views.church_id', $id)->where('content_views.viewable_type', Sermon::class)
            ->where('content_views.created_at', '>=', $from)->whereNull('sermons.deleted_at')
            ->selectRaw("sermons.id, sermons.title, sermons.preacher, SUM(content_views.action = 'view') views, SUM(content_views.action IN ('listen','watch')) listens, SUM(content_views.action = 'download') downloads")
            ->groupBy('sermons.id', 'sermons.title', 'sermons.preacher')->orderByDesc('views')->take(5)->get();

        $topEvents = DB::table('events')->where('events.church_id', $id)->where('events.starts_at', '>=', $from)->whereNull('events.deleted_at')
            ->leftJoin('event_rsvps', fn ($j) => $j->on('event_rsvps.event_id', '=', 'events.id')->where('event_rsvps.status', '!=', 'cancelled'))
            ->selectRaw('events.id, events.title, events.starts_at, COALESCE(SUM(event_rsvps.quantity), 0) rsvps, COUNT(event_rsvps.checked_in_at) checked_in')
            ->groupBy('events.id', 'events.title', 'events.starts_at')->orderByDesc('rsvps')->take(5)->get();

        $liveStreams = $streams()->whereNotNull('started_at')->where('started_at', '>=', $from)->latest('started_at')->take(5)
            ->get(['id', 'title', 'started_at', 'peak_viewers'])
            ->map(fn ($s) => (object) [...(array) $s, 'unique' => DB::table('live_stream_viewers')->where('live_stream_id', $s->id)->count()]);

        $groups = DB::table('small_group_attendance')->join('small_groups', 'small_groups.id', '=', 'small_group_attendance.small_group_id')
            ->where('small_group_attendance.church_id', $id)->where('small_group_attendance.created_at', '>=', $from)
            ->selectRaw("small_groups.name, SUM(small_group_attendance.status = 'present') present, COUNT(*) total")
            ->groupBy('small_groups.name')->orderByDesc('total')->take(8)->get();

        $delivery = DB::table('notification_deliveries')->where('church_id', $id)->where('created_at', '>=', $from)
            ->selectRaw("channel, SUM(status = 'sent') sent, SUM(status = 'failed') failed, SUM(cost) cost")->groupBy('channel')->get();

        $result = compact('headline', 'charts', 'topSermons', 'topEvents', 'liveStreams', 'groups', 'delivery') + ['from' => $from, 'days' => $days];

        if ($withGiving) {
            $gifts = fn () => DB::table('donations')->where('church_id', $id)->where('status', 'succeeded');
            $total = (float) $between($gifts(), 'paid_at')->sum('amount');
            $count = $between($gifts(), 'paid_at')->count();
            $result['headline']['giving'] = ['value' => $total, 'money' => true, 'trend' => $this->trend($total, (float) $between($gifts(), 'paid_at', true)->sum('amount')),
                'note' => __(':n gifts · avg :avg', ['n' => number_format($count), 'avg' => ff_money($count ? $total / $count : 0)])];
            $result['charts']['giving'] = $this->weeklySeries($gifts(), 'paid_at', __('Giving (UGX)'), $weeks, 'amount');
            $result['charts']['givingMethods'] = $this->doughnut($between($gifts(), 'paid_at')->selectRaw('method k, SUM(amount) v')->groupBy('method')->pluck('v', 'k')
                ->mapWithKeys(fn ($v, $k) => [config('faithfellows.payment_methods.'.$k, Str::headline((string) $k)) => (float) $v]));
        }

        return $result;
    }

    /**
     * Headline numbers merged into the super admin dashboard.
     *
     * @return array<string, int|float>
     */
    public function platformHeadline(): array
    {
        return [
            'giving' => (float) DB::table('donations')->where('status', 'succeeded')->where('paid_at', '>=', now()->subDays(30))->sum('amount'),
            'mrr' => $this->mrr(),
            'tickets' => DB::table('support_tickets')->whereIn('status', ['open', 'pending'])->count(),
        ];
    }

    /** Monthly recurring revenue from churches on an active paid plan. */
    public function mrr(): float
    {
        return (float) DB::table('churches')->join('plans', 'plans.id', '=', 'churches.plan_id')
            ->where('churches.status', 'active')->where('churches.billing_status', 'active')->whereNull('churches.deleted_at')
            ->sum(DB::raw("CASE plans.interval WHEN 'year' THEN plans.price / 12 ELSE plans.price END"));
    }

    /**
     * Platform analytics for super admins.
     *
     * @return array<string, mixed>
     */
    public function platformAnalytics(int $days = 90): array
    {
        $from = now()->subDays($days)->startOfDay();
        $prevFrom = $from->copy()->subDays($days);
        $weeks = max(4, min(52, intdiv($days, 7)));

        $count = fn (string $table, string $col, bool $prev = false) => DB::table($table)
            ->when($prev, fn ($q) => $q->whereBetween($col, [$prevFrom, $from]), fn ($q) => $q->where($col, '>=', $from))->count();
        $gifts = fn () => DB::table('donations')->where('status', 'succeeded');
        $giving = (float) $gifts()->where('paid_at', '>=', $from)->sum('amount');
        $mau = DB::table('users')->where('last_login_at', '>=', now()->subDays(30))->count();

        $headline = [
            'churches' => ['value' => DB::table('churches')->where('status', 'active')->whereNull('deleted_at')->count(), 'trend' => $this->trend($count('churches', 'approved_at'), $count('churches', 'approved_at', true)), 'note' => __(':n pending', ['n' => DB::table('churches')->where('status', 'pending')->count()])],
            'users' => ['value' => DB::table('users')->whereNull('deleted_at')->count(), 'trend' => $this->trend($count('users', 'created_at'), $count('users', 'created_at', true)), 'note' => __(':n active this month', ['n' => number_format($mau)])],
            'giving' => ['value' => $giving, 'money' => true, 'trend' => $this->trend($giving, (float) $gifts()->whereBetween('paid_at', [$prevFrom, $from])->sum('amount'))],
            'mrr' => ['value' => $this->mrr(), 'money' => true, 'note' => __(':n paying churches', ['n' => DB::table('churches')->where('billing_status', 'active')->count()])],
            'streams' => ['value' => DB::table('live_streams')->where('started_at', '>=', $from)->count(), 'note' => __(':n peak viewers', ['n' => number_format((int) DB::table('live_streams')->where('started_at', '>=', $from)->sum('peak_viewers'))])],
            'sms' => ['value' => (float) DB::table('notification_deliveries')->where('channel', 'sms')->where('created_at', '>=', $from)->sum('cost'), 'money' => true, 'note' => __(':n SMS sent', ['n' => number_format(DB::table('notification_deliveries')->where('channel', 'sms')->where('status', 'sent')->where('created_at', '>=', $from)->count())])],
        ];

        $charts = [
            'growth' => $this->mergeSeries([
                $this->weeklySeries(DB::table('users'), 'created_at', __('New users'), $weeks),
                $this->weeklySeries(DB::table('subscriptions'), 'subscribed_at', __('New church subscriptions'), $weeks),
            ]),
            'giving' => $this->weeklySeries($gifts(), 'paid_at', __('Giving across all churches (UGX)'), $weeks, 'amount'),
            'methods' => $this->doughnut($gifts()->where('paid_at', '>=', $from)->selectRaw('method k, SUM(amount) v')->groupBy('method')->pluck('v', 'k')
                ->mapWithKeys(fn ($v, $k) => [config('faithfellows.payment_methods.'.$k, Str::headline((string) $k)) => (float) $v])),
            'regions' => ['type' => 'bar', 'data' => (function () {
                $rows = DB::table('churches')->where('status', 'active')->whereNull('deleted_at')->selectRaw("COALESCE(NULLIF(district, ''), city) k, COUNT(*) v")->groupBy('k')->orderByDesc('v')->take(10)->pluck('v', 'k');

                return ['labels' => $rows->keys()->all(), 'datasets' => [['label' => __('Churches'), 'data' => $rows->values()->all()]]];
            })()],
        ];

        $topChurches = DB::table('churches')->where('churches.status', 'active')->whereNull('churches.deleted_at')
            ->leftJoin('subscriptions', fn ($j) => $j->on('subscriptions.church_id', '=', 'churches.id')->where('subscriptions.status', 'active'))
            ->selectRaw('churches.id, churches.name, churches.slug, churches.city, churches.billing_status, COUNT(subscriptions.id) members')
            ->groupBy('churches.id', 'churches.name', 'churches.slug', 'churches.city', 'churches.billing_status')
            ->orderByDesc('members')->take(10)->get()
            ->map(fn ($c) => (object) [...(array) $c, 'giving' => (float) DB::table('donations')->where('church_id', $c->id)->where('status', 'succeeded')->where('paid_at', '>=', $from)->sum('amount')]);

        $channels = DB::table('notification_deliveries')->where('created_at', '>=', $from)
            ->selectRaw("channel, SUM(status = 'sent') sent, SUM(status = 'failed') failed, SUM(cost) cost")->groupBy('channel')->get();

        return compact('headline', 'charts', 'topChurches', 'channels') + ['from' => $from, 'days' => $days];
    }

    /**
     * @param  array<int, array<string, mixed>>  $series  weeklySeries() configs sharing labels
     * @return array<string, mixed>
     */
    public function mergeSeries(array $series): array
    {
        return [
            'type' => 'line',
            'data' => [
                'labels' => $series[0]['data']['labels'] ?? [],
                'datasets' => collect($series)->flatMap(fn ($s) => $s['data']['datasets'])->map(fn ($d) => [...$d, 'fill' => false])->all(),
            ],
            'legend' => true,
        ];
    }

    /**
     * @param  Collection<string, int|float>  $values
     * @return array<string, mixed>
     */
    public function doughnut($values): array
    {
        return [
            'type' => 'doughnut',
            'data' => ['labels' => $values->keys()->values()->all(), 'datasets' => [['data' => $values->values()->map(fn ($v) => (float) $v)->all()]]],
            'legend' => true,
        ];
    }

    /**
     * Percentage change, rounded; null when there is no baseline.
     */
    public function trend(float|int $current, float|int $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Chart.js line config counting rows per week for the last N weeks.
     *
     * @return array<string, mixed>
     */
    public function weeklySeries(Builder|\Illuminate\Database\Query\Builder $query, string $column, string $label, int $weeks = 12, ?string $sumColumn = null): array
    {
        $start = now()->startOfWeek()->subWeeks($weeks - 1);
        $rows = (clone $query)->where($column, '>=', $start)
            ->selectRaw("YEARWEEK($column, 3) as yw, ".($sumColumn ? "SUM($sumColumn)" : 'COUNT(*)').' as total')
            ->groupBy('yw')->pluck('total', 'yw');

        $labels = [];
        $data = [];
        for ($i = 0; $i < $weeks; $i++) {
            $week = $start->copy()->addWeeks($i);
            $labels[] = $week->format('j M');
            $data[] = (float) ($rows[$week->format('oW')] ?? 0);
        }

        return ['type' => 'line', 'data' => ['labels' => $labels, 'datasets' => [['label' => $label, 'data' => $data]]]];
    }

    /**
     * Setup checklist for the church admin dashboard.
     *
     * @return array<int, array{label: string, done: bool, url: string}>
     */
    public function setupChecklist(Church $church): array
    {
        return [
            ['label' => __('Add your logo & cover photo'), 'done' => $church->getFirstMedia('logo') !== null, 'url' => route('church-admin.setup', [$church, 'step' => 'branding'])],
            ['label' => __('Pin your location'), 'done' => $church->lat !== null, 'url' => route('church-admin.setup', [$church, 'step' => 'location'])],
            ['label' => __('Add contact details & social links'), 'done' => (bool) $church->phone, 'url' => route('church-admin.setup', [$church, 'step' => 'contact'])],
            ['label' => __('Add service times'), 'done' => $church->serviceTimes()->exists(), 'url' => route('church-admin.setup', [$church, 'step' => 'services'])],
            ['label' => __('Invite your team'), 'done' => $church->staffMembers()->count() > 1, 'url' => route('church-admin.setup', [$church, 'step' => 'team'])],
        ];
    }
}
