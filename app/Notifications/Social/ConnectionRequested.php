<?php

namespace App\Notifications\Social;

use App\Models\Connection;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** "Grace wants to connect with you" — shown in the member's notification list in the app. */
class ConnectionRequested extends Notification
{
    use Queueable;

    public function __construct(public User $from, public Connection $link) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __(':name wants to connect with you', ['name' => $this->from->name]),
            'body' => $this->link->message,
            'icon' => 'bi-person-plus',
            'url' => url('/'),
            'kind' => 'connection_request',
            'ref' => $this->from->id,
        ];
    }
}
