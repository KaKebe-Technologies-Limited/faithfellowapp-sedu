<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SignupAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Emails the platform owner about one new account, off the request that created it. */
class SendSignupAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public function __construct(public int $userId) {}

    public function handle(SignupAlerts $alerts): void
    {
        if ($user = User::find($this->userId)) {
            $alerts->notify($user);
        }
    }
}
