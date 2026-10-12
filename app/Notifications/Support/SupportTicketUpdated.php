<?php

namespace App\Notifications\Support;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * New ticket / new reply / status change — to the church or to Kakebe support.
 */
class SupportTicketUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public ?int $churchId;

    public string $category = 'announcement';

    /**
     * @param  'created'|'reply'|'status'  $event
     */
    public function __construct(public SupportTicket $ticket, public string $event, public bool $forStaff, public ?string $excerpt = null)
    {
        $this->churchId = $ticket->church_id;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    protected function heading(): string
    {
        return match ($this->event) {
            'created' => __('New support ticket :ref: :subject', ['ref' => $this->ticket->reference, 'subject' => $this->ticket->subject]),
            'status' => __('Ticket :ref is now :status', ['ref' => $this->ticket->reference, 'status' => __(ucfirst($this->ticket->status))]),
            default => __('New reply on ticket :ref', ['ref' => $this->ticket->reference]),
        };
    }

    protected function link(): string
    {
        return $this->forStaff || ! $this->ticket->church_id
            ? route('super-admin.support.show', $this->ticket)
            : route('church-admin.support.show', [$this->ticket->church, $this->ticket]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->heading())->greeting(__('Hello :name,', ['name' => $notifiable->first_name ?? '']));
        if ($this->excerpt) {
            $mail->line(Str::limit(strip_tags($this->excerpt), 600));
        }

        return $mail->action(__('View ticket'), $this->link());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->heading(),
            'body' => $this->excerpt ? Str::limit(strip_tags($this->excerpt), 200) : null,
            'icon' => 'bi-life-preserver',
            'url' => $this->link(),
        ];
    }
}
