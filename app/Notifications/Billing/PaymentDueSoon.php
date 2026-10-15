<?php

namespace App\Notifications\Billing;

use App\Models\Church;
use App\Models\SubscriptionInvoice;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Tomorrow is your payment day": a last reminder the day before the church's monthly fee falls due. */
class PaymentDueSoon extends Notification implements ShouldQueue
{
    use BrandedMail, Queueable;

    public function __construct(public Church $church, public SubscriptionInvoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->branded(
            __('Tomorrow is the payment day for :church', ['church' => $this->church->name]),
            __('Your payment is due tomorrow'),
            [
                __('Hello :name,', ['name' => $notifiable->first_name ?: $notifiable->name]),
                __('The monthly fee for :church is due tomorrow, :date. A minute with your phone is enough: open the billing page, press Pay and approve the mobile money prompt.', ['church' => $this->church->name, 'date' => $this->invoice->period_start->format('j F Y')]),
            ],
            [__('Amount') => ff_money($this->invoice->total, $this->invoice->currency), __('Payment date') => $this->invoice->period_start->format('j F Y')],
            ['label' => __('Pay now'), 'url' => route('church-admin.billing.index', $this->church)],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Your payment is due tomorrow'),
            'body' => ff_money($this->invoice->total, $this->invoice->currency),
            'icon' => 'bi-alarm',
            'url' => route('church-admin.billing.index', $this->church),
            'church' => $this->church->name,
        ];
    }
}
