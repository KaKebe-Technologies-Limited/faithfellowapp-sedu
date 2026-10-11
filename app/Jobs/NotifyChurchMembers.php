<?php

namespace App\Jobs;

use App\Notifications\ChurchNotification;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * Fans a church notification out to its audience in chunks so a church
 * with thousands of members never blocks a web request.
 */
class NotifyChurchMembers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public int $churchId,
        public ChurchNotification $notification,
        public string $audience = 'members',
        public array $options = [],
    ) {}

    public function handle(NotificationService $service): void
    {
        $service->recipients($this->churchId, $this->audience, $this->options)
            ->when($this->options['except_user_id'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))
            // Already on the queue: deliver directly instead of queuing a job per user × channel.
            ->chunkById(500, fn ($users) => Notification::sendNow($users, $this->notification));
    }
}
