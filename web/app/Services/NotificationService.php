<?php

namespace App\Services;

use App\Jobs\NotifyChurchMembers;
use App\Models\Church;
use App\Models\User;
use App\Notifications\ChurchNotification;
use App\Services\Messaging\WebPushSender;
use App\Services\Notifications\PreferenceResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Central place for church → member notifications.
 *
 *  - Who receives it: audience resolution (members, staff, branch, group, stage).
 *  - Which channels: the user's preferences per church × category, then
 *    digest mode (non-urgent → in-app only, summarised daily) and the
 *    fallback chain (push → email → SMS → WhatsApp) for channels the user
 *    can't receive.
 *  - When: quiet hours delay intrusive channels (push, SMS, WhatsApp) until
 *    the window ends; the in-app copy is always immediate.
 */
class NotificationService
{
    /** Channels with a transport in this application. */
    public const SUPPORTED_CHANNELS = ['database', 'mail', 'webpush', 'sms', 'whatsapp', 'broadcast'];

    /** Channels that buzz a phone — held back during quiet hours. */
    public const INTRUSIVE_CHANNELS = ['webpush', 'sms', 'whatsapp'];

    /** Channels that cost the church money per message. */
    public const PAID_CHANNELS = ['sms', 'whatsapp'];

    /** Categories that are time-critical and never folded into the digest. */
    public const NEVER_DIGEST = ['live', 'giving'];

    /** @var array<int, bool> */
    protected array $pushCache = [];

    public function __construct(protected PreferenceResolver $preferences, protected WebPushSender $push) {}

    /**
     * Queue a notification to a church audience (fan-out happens in a job).
     *
     * @param  array<string, mixed>  $options  branch_id, small_group_id, stages, except_user_id
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
            'stage' => $query->whereHas('subscriptions', fn ($q) => $q->where('church_id', $churchId)->where('status', 'active')
                ->whereIn('stage', (array) ($options['stages'] ?? []))),
            default => $query->where(fn ($q) => $q
                ->whereHas('subscriptions', fn ($s) => $s->where('church_id', $churchId)->where('status', 'active'))
                ->orWhereHas('staffMemberships', fn ($s) => $s->where('church_id', $churchId)->where('status', 'active'))),
        };
    }

    /**
     * Deliver to one user now: immediate channels are sent in-process, intrusive
     * channels inside the user's quiet hours are queued until the window ends.
     */
    public function deliver(User $user, ChurchNotification $notification): void
    {
        $channels = $this->channelsFor($user, $notification);
        if ($channels === []) {
            return;
        }

        $later = [];
        foreach ($channels as $i => $channel) {
            if ($until = $this->delayFor($user, $channel, $notification)) {
                $later[$until->toIso8601String()][] = $channel;
                unset($channels[$i]);
            }
        }

        if ($channels !== []) {
            Notification::sendNow($user, $notification, array_values($channels));
        }

        foreach ($later as $until => $deferred) {
            dispatch((new SendQueuedNotifications($user, clone $notification, $deferred))->delay(Carbon::parse($until)));
        }
    }

    /**
     * Channels for one recipient (preferences → digest → fallback chain).
     *
     * @return array<int, string>
     */
    public function channelsFor(object $notifiable, ChurchNotification $notification): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $wanted = $this->preferences->channelsFor($notifiable, $notification->churchId, $notification->category);
        if ($notification->onlyChannels !== null) {
            $wanted = array_intersect($wanted, [...$notification->onlyChannels, 'database']);
        }
        $wanted = array_values(array_unique([...$wanted, ...$notification->forceChannels]));

        if ($wanted === []) {
            return [];
        }

        // Digest mode: keep the in-app copy now, summarise the rest once a day.
        if ($notifiable->digest_mode && ! $notification->urgent && ! in_array($notification->category, self::NEVER_DIGEST, true)) {
            $wanted = array_values(array_intersect($wanted, $notification->forceChannels));
        }

        $external = array_values(array_diff($wanted, ['database']));
        $deliverable = array_values(array_filter($external, fn ($c) => $this->canDeliver($notifiable, $c)));
        $channels = ['database', ...$deliverable];

        // Fallback only when none of the wanted outside channels can reach the member.
        if ($external !== [] && $deliverable === [] && ($fallback = $this->fallbackFor($notifiable, $external[0], $wanted, $notification->urgent))) {
            $channels[] = $fallback;
        }

        if ($this->broadcastingEnabled()) {
            $channels[] = 'broadcast';
        }

        return array_values(array_intersect(array_unique($channels), self::SUPPORTED_CHANNELS));
    }

    /**
     * Whether this user can receive on a channel at all.
     */
    public function canDeliver(User $user, string $channel): bool
    {
        return match ($channel) {
            'database' => true,
            'mail' => $user->hasVerifiedEmail(),
            'webpush' => $this->push->configured() && ($this->pushCache[$user->id] ??= $user->pushSubscriptions()->exists()),
            'sms', 'whatsapp' => filled($user->phone),
            default => false,
        };
    }

    /**
     * Next deliverable channel after $channel in the configured chain
     * (push → email → SMS → WhatsApp). Paid channels are only used as a
     * fallback for urgent messages, so routine alerts never run up SMS costs.
     *
     * @param  array<int, string>  $wanted
     */
    public function fallbackFor(User $user, string $channel, array $wanted, bool $allowPaid = false): ?string
    {
        $chain = config('faithfellows.notification_fallback', []);
        $position = array_search($channel, $chain, true);
        $candidates = $position === false ? $chain : array_slice($chain, $position + 1);

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $wanted, true) || (! $allowPaid && in_array($candidate, self::PAID_CHANNELS, true))) {
                continue;
            }
            if ($this->canDeliver($user, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * When an intrusive channel must wait for the user's quiet hours to end.
     */
    public function delayFor(User $user, string $channel, ChurchNotification $notification): ?Carbon
    {
        if ($notification->urgent || ! in_array($channel, self::INTRUSIVE_CHANNELS, true)) {
            return null;
        }

        return $user->inQuietHours() ? $user->quietHoursEndAt() : null;
    }

    public function broadcastingEnabled(): bool
    {
        return ! in_array(config('broadcasting.default'), [null, 'null', 'log'], true);
    }
}
