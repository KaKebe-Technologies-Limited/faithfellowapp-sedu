<?php

namespace App\Notifications\Billing;

use App\Models\Church;
use App\Models\SubscriptionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Trial ending soon, or an invoice is overdue (with the suspension date).
 */
class SubscriptionExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public ?int $churchId;

    public string $category = 'giving';

    public function __construct(public Church $church, public ?SubscriptionInvoice $invoice)
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

        if (! $this->invoice) {
            return $mail->subject(__('Your free hosting period ends soon'))
                ->line(__('The free first year for :church ends on :date. From then the Starter plan is UGX :price/month.', [
                    'church' => $this->church->name, 'date' => $this->church->trial_ends_at?->format('j F Y'),
                    'price' => number_format(config('faithfellows.pricing.plan_price')),
                ]))
                ->action(__('View billing'), route('church-admin.billing.index', $this->church));
        }

        $suspendOn = $this->invoice->due_date->copy()->addDays(config('faithfellows.pricing.grace_days'));

        return $mail->subject(__('Invoice :n is overdue', ['n' => $this->invoice->number]))
            ->line(__('Invoice :n for :amount was due on :due.', ['n' => $this->invoice->number, 'amount' => ff_money($this->invoice->total), 'due' => $this->invoice->due_date->format('j M Y')]))
            ->line(__('To avoid interruption, please pay before :date. You pay with mobile money: you get a prompt on your phone.', ['date' => $suspendOn->format('j M Y')]))
            ->action(__('Pay now'), route('church-admin.billing.index', $this->church));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->invoice ? __('Invoice :n is overdue', ['n' => $this->invoice->number]) : __('Free hosting ends soon'),
            'body' => $this->invoice ? ff_money($this->invoice->total) : $this->church->trial_ends_at?->format('j M Y'),
            'icon' => 'bi-receipt',
            'url' => route('church-admin.billing.index', $this->church),
        ];
    }
}
