<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The six-digit code the app asks for on "Forgot password". Sent synchronously — the member is waiting on the screen.
 */
class PasswordResetCode extends Notification
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
            ->subject(__('Your FaithFellows password reset code'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('Use this code in the app to choose a new password:'))
            ->line('**'.$this->code.'**')
            ->line(__('The code expires in 15 minutes. If you did not ask to reset your password, you can ignore this message — your password has not changed.'));
    }
}
