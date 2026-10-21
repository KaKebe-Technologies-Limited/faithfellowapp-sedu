<?php

namespace App\Services;

use App\Jobs\SendPushBatch;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Messaging\ExpoPushSender;
use Illuminate\Support\Collection;

/**
 * One message to many phones: everyone who has the app, or a chosen list of members. Only people who have that kind of news switched on
 * (Notification settings) and are not in their quiet hours get it. The work runs right after the request or command that started it, so it does
 * not wait for a queue worker (a shared server may have none running).
 */
class PushBroadcaster
{
    public function __construct(protected DevicePushService $push, protected ExpoPushSender $sender) {}

    /**
     * @param  array<string, mixed>  $message  title, body, data, channelId
     * @param  array<int, int>  $except  members who are left out (the person who just posted it)
     * @return int people it was sent to, before their own notification settings
     */
    public function toEveryone(string $category, array $message, array $except = []): int
    {
        return $this->send(DeviceToken::query()->distinct()->orderBy('user_id')->pluck('user_id'), $category, $message, $except);
    }

    /**
     * @param  iterable<int>  $userIds
     * @param  array<string, mixed>  $message
     * @param  array<int, int>  $except
     */
    public function toUsers(iterable $userIds, string $category, array $message, array $except = []): int
    {
        return $this->send(collect($userIds)->unique()->values(), $category, $message, $except);
    }

    /** How many members could be reached if every one of them had the app on a phone. */
    public function members(): int
    {
        return User::where('status', 'active')->count();
    }

    /** How many people have the app on a phone. */
    public function audience(): int
    {
        return (int) DeviceToken::query()->distinct()->count('user_id');
    }

    /**
     * Pushes to the phones of these members who want it. Returns how many phones the push service accepted it for.
     *
     * @param  array<int, int>  $userIds
     * @param  array<string, mixed>  $message
     */
    public function deliver(array $userIds, string $category, array $message): int
    {
        $tokens = collect();
        User::whereIn('id', $userIds)->where('status', 'active')->with('deviceTokens')->each(function (User $user) use ($category, &$tokens) {
            if ($this->push->wants($user, $category)) {
                $tokens = $tokens->merge($user->deviceTokens);
            }
        });

        return $tokens->isEmpty() ? 0 : $this->sender->send($tokens, $message);
    }

    /**
     * @param  Collection<int, int>  $ids
     * @param  array<string, mixed>  $message
     * @param  array<int, int>  $except
     */
    protected function send(Collection $ids, string $category, array $message, array $except): int
    {
        $ids = $ids->diff($except)->values();
        $ids->chunk(200)->each(fn ($chunk) => SendPushBatch::dispatchAfterResponse($chunk->values()->all(), $category, $message));

        return $ids->count();
    }
}
