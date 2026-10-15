<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\DevicePushService;
use App\Services\Messaging\ExpoPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** One inspiring message pushed to a batch of members' phones (those who want it), in a single call to the push service. */
class SendInspirationBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, mixed>  $message  title, body, data, channelId
     */
    public function __construct(public array $userIds, public array $message) {}

    public function handle(DevicePushService $push, ExpoPushSender $sender): void
    {
        $tokens = collect();
        User::whereIn('id', $this->userIds)->where('status', 'active')->with('deviceTokens')->each(function (User $user) use ($push, &$tokens) {
            if ($push->wants($user, 'inspiration')) {
                $tokens = $tokens->merge($user->deviceTokens);
            }
        });

        if ($tokens->isNotEmpty()) {
            $sender->send($tokens, $this->message);
        }
    }
}
