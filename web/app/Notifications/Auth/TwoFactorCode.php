<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously — the user is waiting on the challenge screen.
 */
class TwoFactorCode extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your FaithFellows sign-in code'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('Use this code to finish signing in:'))
            ->line('**'.$this->code.'**')
            ->line(__('The code expires in 10 minutes. If you did not try to sign in, please change your password.'));
    }
}
