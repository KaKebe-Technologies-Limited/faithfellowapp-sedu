<?php

namespace App\Notifications\Channels;

use App\Services\Messaging\WebPushSender;
use Illuminate\Notifications\Notification;

/**
 * Browser push to every device the user subscribed (service worker shows it).
 */
class WebPushChannel
{
    public function __construct(protected WebPushSender $sender) {}

    /**
     * @return int devices reached
     */
    public function send(object $notifiable, Notification $notification): int
    {
        if (! method_exists($notification, 'toWebPush') || ! method_exists($notifiable, 'pushSubscriptions')) {
            return 0;
        }

        $subscriptions = $notifiable->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        return $this->sender->send($subscriptions, [
            'icon' => asset('images/icon-192.png'),
            'badge' => asset('images/icon-192.png'),
            ...$notification->toWebPush($notifiable),
        ]);
    }
}
