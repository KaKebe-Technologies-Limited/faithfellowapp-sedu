<?php

namespace App\Notifications\Billing;

use App\Models\Church;
use App\Models\SubscriptionInvoice;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your church's monthly fee is due on …": sent a week before the payment day to the church's owner and admins, so there is time to pay.
 */
class InvoiceIssued extends Notification implements ShouldQueue
{
    use BrandedMail, Queueable;

    public function __construct(public Church $church, public SubscriptionInvoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $grace = (int) config('faithfellows.pricing.grace_days');

        return $this->branded(
            __('Payment for :church is due on :date', ['church' => $this->church->name, 'date' => $this->invoice->period_start->format('j F Y')]),
            __('Your next payment is on :date', ['date' => $this->invoice->period_start->format('j F')]),
            [
                __('Hello :name,', ['name' => $notifiable->first_name ?: $notifiable->name]),
                __('This is a friendly reminder that the monthly FaithFellows fee for :church is coming up in a week. It pays for the servers, the daily running of the platform and the support that keep your church portal and the app working for your members.', ['church' => $this->church->name]),
                __('You pay with mobile money: you get a prompt on your phone and approve it with your PIN.'),
            ],
            [
                __('Amount') => ff_money($this->invoice->total, $this->invoice->currency),
                __('Payment date') => $this->invoice->period_start->format('j F Y'),
                __('Period covered') => $this->invoice->period_start->format('j M').' – '.$this->invoice->period_end->format('j M Y'),
                __('Invoice') => $this->invoice->number,
            ],
            ['label' => __('Pay with mobile money'), 'url' => route('church-admin.billing.index', $this->church)],
            __('If the payment is more than :days days late, the church page and its access in the app are paused until it is paid. Paying brings everything back at once.', ['days' => $grace]),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Your next payment is on :date', ['date' => $this->invoice->period_start->format('j M')]),
            'body' => ff_money($this->invoice->total, $this->invoice->currency).' · '.$this->invoice->number,
            'icon' => 'bi-receipt',
            'url' => route('church-admin.billing.index', $this->church),
            'church' => $this->church->name,
        ];
    }
}
