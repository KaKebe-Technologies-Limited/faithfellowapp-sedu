<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Church\NewSubscriber;
use App\Notifications\Member\WelcomeMember;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Follow / unfollow churches. Re-subscribing re-activates the same row so
 * history (stage, first subscribed date) is preserved.
 */
class SubscriptionService
{
    public function subscribe(User $user, Church $church, ?string $source = null): Subscription
    {
        abort_unless($church->isActive(), 422, __('This church is not accepting subscribers right now.'));

        $existing = Subscription::where('user_id', $user->id)->where('church_id', $church->id)->first();
        abort_if($existing?->status === 'blocked', 403, __('You cannot subscribe to this church.'));

        if ($existing?->isActive()) {
            return $existing;
        }

        $status = $church->requires_member_approval ? 'pending' : 'active';

        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id, 'church_id' => $church->id],
            [
                'status' => $status,
                'source' => $existing?->source ?? $source,
                'share_email' => $user->email_consent,
                'subscribed_at' => $existing?->subscribed_at ?? now(),
                'unsubscribed_at' => null,
                'approved_at' => $status === 'active' ? now() : null,
            ],
        );

        $user->flushSubscriptionCache();

        if ($status === 'active') {
            $user->notify(new WelcomeMember($church));
        }
        Notification::send($this->admins($church), new NewSubscriber($church, $user, $status === 'pending'));

        return $subscription;
    }

    public function unsubscribe(User $user, Church $church): void
    {
        Subscription::where('user_id', $user->id)->where('church_id', $church->id)
            ->whereIn('status', ['active', 'pending'])
            ->update(['status' => 'left', 'unsubscribed_at' => now()]);

        $user->flushSubscriptionCache();
    }

    public function approve(Subscription $subscription): void
    {
        $subscription->update(['status' => 'active', 'approved_at' => now()]);
        Audit::log('member.approved', $subscription, $subscription->user?->name);
        $subscription->user?->notify(new WelcomeMember($subscription->church));
    }

    public function block(Subscription $subscription): void
    {
        $subscription->update(['status' => 'blocked']);
        Audit::log('member.blocked', $subscription, $subscription->user?->name);
    }

    public function unblock(Subscription $subscription): void
    {
        $subscription->update(['status' => 'active']);
        Audit::log('member.unblocked', $subscription, $subscription->user?->name);
    }

    /**
     * @return Collection<int, User>
     */
    protected function admins(Church $church)
    {
        return User::whereIn('id', $church->staffMembers()->where('status', 'active')
            ->where(fn ($q) => $q->where('is_admin', true)->orWhereHas('role', fn ($r) => $r->where('permissions', 'like', '%members.manage%')))
            ->pluck('user_id'))->get();
    }
}
