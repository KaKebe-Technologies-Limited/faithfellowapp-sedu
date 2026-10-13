<?php

namespace App\Notifications\Social;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** "Daniel accepted your request" — you can now message each other. */
class ConnectionAccepted extends Notification
{
    use Queueable;

    public function __construct(public User $by) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __(':name accepted your request', ['name' => $this->by->name]),
            'body' => __('You can now send each other messages.'),
            'icon' => 'bi-person-check',
            'url' => url('/'),
            'kind' => 'connection_accepted',
            'ref' => $this->by->id,
        ];
    }
}
