<?php

namespace App\Listeners;

use App\Events\DonationSucceeded;
use App\Services\CampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateCampaignProgress implements ShouldQueue
{
    public function __construct(protected CampaignService $campaigns) {}

    public function handle(DonationSucceeded $event): void
    {
        $campaign = $event->donation->campaign()->first();
        if ($campaign) {
            $this->campaigns->recalculate($campaign);
        }
    }
}
