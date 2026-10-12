<?php

namespace App\Notifications\Giving;

use App\Models\Campaign;
use App\Notifications\ChurchNotification;

class CampaignMilestoneReached extends ChurchNotification
{
    public string $category = 'giving';

    public function __construct(public Campaign $campaign, public int $percent)
    {
        $this->churchId = $campaign->church_id;
    }

    public function title(): string
    {
        return $this->percent >= 100
            ? __('🎉 :title reached its goal!', ['title' => $this->campaign->title])
            : __(':title is :percent% funded!', ['title' => $this->campaign->title, 'percent' => $this->percent]);
    }

    public function body(): ?string
    {
        return __(':raised raised of :goal. Thank you for your generosity!', [
            'raised' => ff_money($this->campaign->raised_amount, $this->campaign->currency),
            'goal' => ff_money($this->campaign->goal_amount, $this->campaign->currency),
        ]);
    }

    public function url(): string
    {
        return route('campaigns.show', $this->campaign);
    }

    public function icon(): string
    {
        return 'bi-flag';
    }

    public function churchName(): ?string
    {
        return $this->campaign->church?->name;
    }
}
