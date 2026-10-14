<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\DevicePushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** One push to all of a member's phones, off the request that caused it. */
class SendDevicePush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    /** @param  array<string, mixed>  $message */
    public function __construct(public int $userId, public array $message) {}

    public function handle(DevicePushService $push): void
    {
        if ($user = User::find($this->userId)) {
            $push->sendNow($user, $this->message);
        }
    }
}
