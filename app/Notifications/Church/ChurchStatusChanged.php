<?php

namespace App\Notifications\Church;

use App\Models\Church;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChurchStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public ?int $churchId;

    public string $category = 'announcement';

    public function __construct(public Church $church, public string $change, public ?string $reason = null)
    {
        $this->churchId = $church->id;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting(__('Hello :name,', ['name' => $notifiable->first_name]));

        return match ($this->change) {
            'approved' => $mail->subject(__(':church is live on FaithFellows 🎉', ['church' => $this->church->name]))
                ->line(__('Great news — :church has been approved. Your church website is ready at :url.', ['church' => $this->church->name, 'url' => ff_church_url($this->church)]))
                ->line(__('Finish the setup wizard to add your logo, service times, giving channels and staff.'))
                ->action(__('Open church dashboard'), route('church-admin.dashboard', $this->church)),
            'suspended' => $mail->subject(__(':church has been suspended', ['church' => $this->church->name]))
                ->line(__('Your church space has been suspended.'))
                ->when($this->reason, fn ($m) => $m->line(__('Reason: :reason', ['reason' => $this->reason])))
                ->line(__('Please contact :email to resolve this.', ['email' => config('faithfellows.company.email')])),
            default => $mail->subject(__(':church is active again', ['church' => $this->church->name]))
                ->line(__('Your church space has been reactivated.'))
                ->action(__('Open church dashboard'), route('church-admin.dashboard', $this->church)),
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => match ($this->change) {
                'approved' => __(':church was approved', ['church' => $this->church->name]),
                'suspended' => __(':church was suspended', ['church' => $this->church->name]),
                default => __(':church is active again', ['church' => $this->church->name]),
            },
            'body' => $this->reason,
            'icon' => $this->change === 'suspended' ? 'bi-pause-circle' : 'bi-check-circle',
            'url' => route('church-admin.dashboard', $this->church),
        ];
    }
}
