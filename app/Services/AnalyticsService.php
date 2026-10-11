<?php

namespace App\Services;

use App\Models\Church;
use Illuminate\Database\Eloquent\Builder;

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
