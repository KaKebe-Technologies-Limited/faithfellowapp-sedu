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
use Illuminate\Validation\ValidationException;

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

        // Members follow at most N churches at a time (pending requests count too).
        $limit = (int) config('faithfellows.limits.subscriptions', 3);
        if ($existing?->status !== 'pending' && $this->activeCount($user) >= $limit) {
            throw ValidationException::withMessages(['church' => trans_choice(
                'You can follow up to :count church at a time. Unsubscribe from one in My churches to follow :church.|You can follow up to :count churches at a time. Unsubscribe from one in My churches to follow :church.',
                $limit, ['church' => $church->name],
            )]);
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

    /**
     * The platform admin makes a registered user a follower of a church. It is not the member's own choice, so it does not count against the
     * limit of churches a member follows and the church's own approval is not asked for; a member the church has blocked stays blocked.
     *
     * @return 'added'|'already'|'blocked'|'church_inactive'|'user_inactive'
     */
    public function assign(User $user, Church $church, ?User $by = null, string $stage = 'member', bool $notify = true): string
    {
        if (! $church->isActive()) {
            return 'church_inactive';
        }
        if ($user->status === 'suspended') {
            return 'user_inactive';
        }
        $existing = Subscription::where('user_id', $user->id)->where('church_id', $church->id)->first();
        if ($existing?->status === 'blocked') {
            return 'blocked';
        }
        if ($existing?->isActive()) {
            return 'already';
        }

        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id, 'church_id' => $church->id],
            [
                'status' => 'active',
                'stage' => array_key_exists($stage, Subscription::STAGES) ? $stage : 'member',
                'source' => $existing?->source ?? 'assigned',
                'share_email' => $existing?->share_email ?? (bool) $user->email_consent,
                'subscribed_at' => $existing?->subscribed_at ?? now(),
                'unsubscribed_at' => null,
                'approved_at' => now(),
            ],
        );
        $user->flushSubscriptionCache();
        Audit::log('member.assigned', $subscription, __(':user added to :church', ['user' => $user->name, 'church' => $church->name]), [], ['church_id' => $church->id, 'user_id' => $user->id, 'by' => $by?->id], $church->id);

        if ($notify) {
            $user->notify(new WelcomeMember($church));
        }

        return 'added';
    }

    /** Churches the user currently follows or has asked to join. */
    public function activeCount(User $user): int
    {
        return Subscription::where('user_id', $user->id)->whereIn('status', ['active', 'pending'])->count();
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
