<?php

namespace App\Notifications\Channels;

use App\Models\DirectMessage;
use App\Services\Messaging\ExpoPushSender;
use Illuminate\Notifications\Notification;

/**
 * Push to every phone the member has the app on (the notification describes itself in toPush()).
 */
class ExpoPushChannel
{
    public function __construct(protected ExpoPushSender $sender) {}

    /**
     * @return int phones reached
     */
    public function send(object $notifiable, Notification $notification): int
    {
        if (! method_exists($notification, 'toPush') || ! method_exists($notifiable, 'deviceTokens')) {
            return 0;
        }

        $tokens = $notifiable->deviceTokens()->get();
        if ($tokens->isEmpty()) {
            return 0;
        }

        return $this->sender->send($tokens, [
            'badge' => $notifiable->unreadNotifications()->count() + DirectMessage::where('recipient_id', $notifiable->id)->whereNull('read_at')->count(),
            ...$notification->toPush($notifiable),
        ]);
    }
}
