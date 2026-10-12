<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLoginLink extends Notification
{
    use Queueable;

    public function __construct(public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your FaithFellows sign-in link'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('Tap the button below to sign in. The link expires in 15 minutes and can only be used by you.'))
            ->action(__('Sign in to FaithFellows'), $this->url)
            ->line(__('If you did not request this, you can safely ignore this email.'));
    }
}
