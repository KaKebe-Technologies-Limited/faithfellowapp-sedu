<?php

namespace App\Notifications\Support;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email reply to someone who used the public contact form.
 */
class ContactReply extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactMessage $message, public string $body, public string $from) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Re: '.($this->message->subject ?: __('Your message to FaithFellows')))
            ->greeting(__('Hello :name,', ['name' => $this->message->name]));

        foreach (preg_split("/\R{2,}/", trim($this->body)) as $paragraph) {
            $mail->line($paragraph);
        }

        return $mail->salutation(__('Blessings,')."\n".$this->from."\n".config('faithfellows.company.name', 'Kakebe Technologies Limited'))
            ->replyTo(config('faithfellows.company.email', config('mail.from.address')));
    }
}
