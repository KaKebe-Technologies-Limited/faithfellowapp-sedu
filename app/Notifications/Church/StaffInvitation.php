<?php

namespace App\Notifications\Church;

use App\Models\Church;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when someone is added as church staff. New accounts get a link to
 * choose their password (a standard password-reset token).
 */
class StaffInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public ?int $churchId;

    public string $category = 'announcement';

    public function __construct(public Church $church, public string $roleName, public ?string $passwordToken = null)
    {
        $this->churchId = $church->id;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('You have been added to :church on FaithFellows', ['church' => $this->church->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('You have been added to :church as :role.', ['church' => $this->church->name, 'role' => $this->roleName]));

        if ($this->passwordToken) {
            return $mail->line(__('An account has been created for you. Choose a password to get started.'))
                ->action(__('Set your password'), route('password.reset', ['token' => $this->passwordToken, 'email' => $notifiable->email]));
        }

        return $mail->action(__('Open church dashboard'), route('church-admin.dashboard', $this->church));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('You joined :church staff', ['church' => $this->church->name]),
            'body' => __('Role: :role', ['role' => $this->roleName]),
            'icon' => 'bi-person-badge',
            'url' => route('church-admin.dashboard', $this->church),
        ];
    }
}
