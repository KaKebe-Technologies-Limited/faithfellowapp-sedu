<?php

namespace App\Policies;

use App\Models\Donation;
use App\Models\User;

class DonationPolicy
{
    /** Givers see their own gifts; finance staff see their church's gifts. */
    public function view(User $user, Donation $donation): bool
    {
        return $donation->user_id === $user->id || $user->canInChurch($donation->church_id, 'giving.view');
    }

    public function refund(User $user, Donation $donation): bool
    {
        return $user->canInChurch($donation->church_id, 'giving.manage');
    }

    public function reconcile(User $user, Donation $donation): bool
    {
        return $user->canInChurch($donation->church_id, 'giving.manage');
    }
}
