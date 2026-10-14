<?php

namespace App\Notifications\Member;

use App\Models\Church;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeMember extends Notification implements ShouldQueue
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
            ->subject(__('Welcome to :church', ['church' => $this->church->name]))
            ->greeting(__('Welcome, :name!', ['name' => $notifiable->first_name]))
            ->line(__('You are now following :church on FaithFellows. You will see their sermons, live services, events and updates in your feed.', ['church' => $this->church->name]))
            ->action(__('Visit :church', ['church' => $this->church->name]), route('churches.show', $this->church))
            ->line(__('You can choose which notifications you get from this church — or unsubscribe — at any time.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Welcome to :church', ['church' => $this->church->name]),
            'body' => __('Their updates will now appear in your feed.'),
            'icon' => 'bi-house-heart',
            'url' => route('churches.show', $this->church),
        ];
    }
}
