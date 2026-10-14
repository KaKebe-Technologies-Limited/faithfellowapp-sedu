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
            ->subject(__('Your FaithFellows password reset code: :code', ['code' => $this->code]))
            ->view(['emails.password-code', 'emails.password-code-text'], ['name' => $notifiable->first_name ?: $notifiable->name, 'code' => $this->code]);
    }
}
