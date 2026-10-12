<?php

namespace App\Services;

use App\Models\Church;
use Illuminate\Database\Eloquent\Builder;
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
