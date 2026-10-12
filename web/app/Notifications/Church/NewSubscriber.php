<?php

namespace App\Notifications\Church;

use App\Models\Church;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells church admins someone subscribed (or is awaiting approval).
 */
class NewSubscriber extends Notification implements ShouldQueue
{
    use Queueable;

    public ?int $churchId;

    public string $category = 'announcement';

    public function __construct(public Church $church, public User $subscriber, public bool $pending = false)
    {
        $this->churchId = $church->id;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->pending
                ? __(':name wants to join :church', ['name' => $this->subscriber->name, 'church' => $this->church->name])
                : __(':name subscribed to :church', ['name' => $this->subscriber->name, 'church' => $this->church->name]),
            'body' => $this->pending ? __('Approve them in the member manager.') : null,
            'icon' => 'bi-person-plus',
            'url' => route('church-admin.members.index', [$this->church, 'status' => $this->pending ? 'pending' : null]),
        ];
    }
}
