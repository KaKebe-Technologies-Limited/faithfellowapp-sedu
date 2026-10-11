<?php

namespace App\Services;

use App\Models\Church;

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
            'staff' => $church->staffMembers()->where('status', 'active')->count(),
            'branches' => $church->branches()->count(),
            'service_times' => $church->serviceTimes()->count(),
            'storage_mb' => round((float) \DB::table('media')->where('church_id', $church->id)->sum('size') / 1048576, 1),
        ];
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
