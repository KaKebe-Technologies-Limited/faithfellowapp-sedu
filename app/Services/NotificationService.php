<?php

namespace App\Services;

use App\Jobs\NotifyChurchMembers;
use App\Models\Church;
use App\Models\User;
use App\Notifications\ChurchNotification;
use App\Services\Notifications\PreferenceResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Central place for church → member notifications.
 *
 *  - Who receives it: audience resolution (members, staff, branch, group).
 *  - How: channel selection from the user's preferences per church/category.
 */
class NotificationService
{
    /** Channels with a working transport in this deployment. */
    public const SUPPORTED_CHANNELS = ['database', 'mail'];

    public function __construct(protected PreferenceResolver $preferences) {}

    /**
     * Queue a notification to a church audience (fan-out happens in a job).
     *
     * @param  array<string, mixed>  $options  branch_id, small_group_id
     */
    public function notifyChurch(Church $church, ChurchNotification $notification, string $audience = 'members', array $options = []): void
    {
        $notification->churchId ??= $church->id;
        NotifyChurchMembers::dispatch($church->id, $notification, $audience, $options);
    }

    /**
     * Recipients for an audience within a church.
     *
     * @param  array<string, mixed>  $options
     */
    public function recipients(int $churchId, string $audience, array $options = []): Builder
    {
        $query = User::query()->where('status', 'active');

        return match ($audience) {
            'staff' => $query->whereHas('staffMemberships', fn ($q) => $q->where('church_id', $churchId)->where('status', 'active')),
            'branch' => $query->whereHas('subscriptions', fn ($q) => $q->where('church_id', $churchId)->where('status', 'active')
                ->where('branch_id', $options['branch_id'] ?? 0)),
            'group' => $query->whereIn('id', \DB::table('small_group_members')->where('small_group_id', $options['small_group_id'] ?? 0)->where('status', 'active')->select('user_id')),
            default => $query->where(fn ($q) => $q
                ->whereHas('subscriptions', fn ($s) => $s->where('church_id', $churchId)->where('status', 'active'))
                ->orWhereHas('staffMemberships', fn ($s) => $s->where('church_id', $churchId)->where('status', 'active'))),
        };
    }

    /**
     * Channels for one recipient, honouring their preferences.
     *
     * @return array<int, string|class-string>
     */
    public function channelsFor(object $notifiable, ChurchNotification $notification): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $wanted = $this->preferences->channelsFor($notifiable, $notification->churchId, $notification->category);
        $wanted = array_values(array_unique([...$wanted, ...$notification->forceChannels]));

        // Always keep an in-app copy when anything is delivered.
        if ($wanted !== [] && ! in_array('database', $wanted, true)) {
            $wanted[] = 'database';
        }

        if (! $notifiable->hasVerifiedEmail()) {
            $wanted = array_diff($wanted, ['mail']);
        }

        return array_values(array_intersect($wanted, static::SUPPORTED_CHANNELS));
    }
}
