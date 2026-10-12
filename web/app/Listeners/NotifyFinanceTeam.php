<?php

namespace App\Listeners;

use App\Events\DonationSucceeded;
use App\Models\User;
use App\Notifications\Giving\DonationReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells church finance staff (admins + roles with giving.view) about new gifts.
 */
class NotifyFinanceTeam implements ShouldQueue
{
    public function handle(DonationSucceeded $event): void
    {
        $donation = $event->donation->fresh(['church', 'user']);
        if (! $donation) {
            return;
        }

        $staffIds = $donation->church->staffMembers()->with('role')->where('status', 'active')->get()
            ->filter(fn ($s) => $s->is_admin || in_array('giving.view', $s->role?->permissions ?? [], true))
            ->pluck('user_id');

        Notification::send(User::whereIn('id', $staffIds)->get(), new DonationReceived($donation));
    }
}
