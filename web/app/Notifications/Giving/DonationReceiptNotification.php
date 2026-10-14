<?php

namespace App\Notifications\Giving;

use App\Models\Donation;
use App\Notifications\ChurchNotification;
use App\Services\ReceiptService;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Instant thank-you + receipt to the giver (email with PDF, in-app, optional SMS).
 */
class DonationReceiptNotification extends ChurchNotification
{
    public string $category = 'giving';

    public function __construct(public Donation $donation)
    {
        $this->churchId = $donation->church_id;
        // Receipts always go by email when the giver has a verified address.
        $this->forceChannels = ['database', 'mail'];
    }

    public function title(): string
    {
        return __('Thank you! :amount received by :church', ['amount' => ff_money($this->donation->amount, $this->donation->currency), 'church' => $this->donation->church?->name]);
    }

    public function body(): ?string
    {
        return __('Receipt :number · :category', ['number' => $this->donation->receipt_number, 'category' => $this->donation->category_label])
            .($this->donation->campaign ? ' · '.$this->donation->campaign->title : '');
    }

    public function url(): string
    {
        return route('giving.receipt', $this->donation->reference);
    }

    public function icon(): string
    {
        return 'bi-receipt';
    }

    public function churchName(): ?string
    {
        return $this->donation->church?->name;
    }

    /** The thank-you email every giver gets: a warm, branded note with the gift's details, and the receipt as a PDF. */
    public function toMail(object $notifiable): MailMessage
    {
        $receipts = app(ReceiptService::class);
        $donation = $this->donation;
        $church = $donation->church?->name ?? config('app.name');

        $mail = (new MailMessage)
            ->subject(__('Thank you for your gift to :church', ['church' => $church]))
            ->view(['emails.donation-thanks', 'emails.donation-thanks-text'], [
                'name' => $notifiable->first_name ?: $notifiable->name,
                'amount' => ff_money($donation->amount, $donation->currency),
                'church' => $church,
                'reason' => $donation->category_label,
                'campaign' => $donation->campaign,
                'date' => ($donation->paid_at ?? $donation->created_at)?->format('j F Y, H:i'),
                'method' => $donation->method_label,
                'receipt' => $donation->receipt_number ?: $donation->reference,
                'url' => route('giving.index'),
            ]);

        // The receipt is a PDF; if it cannot be made right now, the thank-you still goes.
        try {
            $mail->attach($receipts->path($donation), ['as' => $receipts->filename($donation), 'mime' => 'application/pdf']);
        } catch (\Throwable $e) {
            report($e);
        }

        return $mail;
    }

    public function toSms(object $notifiable): string
    {
        return __('Thank you! :church received :amount (:category). Receipt :number', [
            'church' => $this->donation->church?->name,
            'amount' => ff_money($this->donation->amount, $this->donation->currency),
            'category' => $this->donation->category_label,
            'number' => $this->donation->receipt_number,
        ]);
    }
}
