<?php

namespace App\Jobs;

use App\Services\Messaging\ExpoPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Looks at Expo's receipts for earlier pushes and forgets phones that no longer exist. */
class CheckExpoReceipts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    /** @param  array<string, string>  $tickets  ticket id => token */
    public function __construct(public array $tickets) {}

    public function handle(ExpoPushSender $sender): void
    {
        $sender->checkReceipts($this->tickets);
    }
}
