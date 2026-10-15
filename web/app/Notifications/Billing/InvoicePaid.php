<?php

namespace App\Notifications\Billing;

use App\Models\Church;
use App\Models\SubscriptionInvoice;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Thank you, payment received": the receipt for the church's monthly fee, with the next payment date. */
class InvoicePaid extends Notification implements ShouldQueue
{
    use BrandedMail, Queueable;

    public function __construct(public Church $church, public SubscriptionInvoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $next = $this->church->paid_until;

        return $this->branded(
            __('Payment received for :church. Thank you!', ['church' => $this->church->name]),
            __('Thank you, payment received'),
            [
                __('Hello :name,', ['name' => $notifiable->first_name ?: $notifiable->name]),
                __('We have received the FaithFellows fee for :church. Your church portal and the app stay active.', ['church' => $this->church->name]),
            ],
            array_filter([
                __('Amount') => ff_money($this->invoice->total, $this->invoice->currency),
                __('Invoice') => $this->invoice->number,
                __('Paid on') => $this->invoice->paid_at?->format('j F Y'),
                __('Next payment') => $next?->format('j F Y'),
            ]),
            ['label' => __('View billing'), 'url' => route('church-admin.billing.index', $this->church)],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Payment received. Thank you!'),
            'body' => ff_money($this->invoice->total, $this->invoice->currency).' · '.$this->invoice->number,
            'icon' => 'bi-check-circle',
            'url' => route('church-admin.billing.index', $this->church),
            'church' => $this->church->name,
        ];
    }
}
