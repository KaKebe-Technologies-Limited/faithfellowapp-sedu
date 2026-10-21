<?php

namespace App\Jobs;

use App\Services\PushBroadcaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** One message pushed to a batch of members' phones (those who want that kind of news), in a single call to the push service. */
class SendPushBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, mixed>  $message  title, body, data, channelId
     */
    public function __construct(public array $userIds, public string $category, public array $message) {}

    public function handle(PushBroadcaster $push): void
    {
        $push->deliver($this->userIds, $this->category, $this->message);
    }
}
