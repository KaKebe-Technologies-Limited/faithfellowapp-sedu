<?php

namespace App\Notifications\Church;

use App\Models\Church;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChurchApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public ?int $churchId;

    public string $category = 'announcement';

    public function __construct(public Church $church)
    {
        $this->churchId = $church->id;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New church application: :name', ['name' => $this->church->name]))
            ->line(__(':name (:city) has applied to join FaithFellows.', ['name' => $this->church->name, 'city' => $this->church->city ?? '—']))
            ->action(__('Review application'), route('super-admin.churches.show', $this->church));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('New church application'),
            'body' => __(':name is awaiting approval.', ['name' => $this->church->name]),
            'icon' => 'bi-building-add',
            'url' => route('super-admin.churches.show', $this->church),
        ];
    }
}
