<?php

namespace App\Listeners;

use App\Events\DonationSucceeded;
use App\Services\CampaignService;

/**
 * Counts a paid gift into its campaign's total at once. It is not queued: a shared server may have no queue worker running, and a campaign must show
 * what has been given (the totals also correct themselves when the campaign is opened, see CampaignService::healCampaign).
 */
class UpdateCampaignProgress
{
    public function __construct(protected CampaignService $campaigns) {}

    public function handle(DonationSucceeded $event): void
    {
        $campaign = $event->donation->campaign()->first();
        if ($campaign) {
            // The gift is already recorded as paid; if counting it fails the error is reported and the campaign corrects itself the next time it is opened.
            rescue(fn () => $this->campaigns->recalculate($campaign));
        }
    }
}
