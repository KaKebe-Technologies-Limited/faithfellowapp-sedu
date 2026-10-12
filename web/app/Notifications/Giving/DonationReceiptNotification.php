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

    public function toMail(object $notifiable): MailMessage
    {
        $receipts = app(ReceiptService::class);

        return (new MailMessage)
            ->subject(__('Your receipt from :church (:number)', ['church' => $this->donation->church?->name, 'number' => $this->donation->receipt_number]))
            ->greeting(__('Thank you, :name!', ['name' => $notifiable->first_name]))
            ->line(__('We have received your gift of **:amount** to :church.', ['amount' => ff_money($this->donation->amount, $this->donation->currency), 'church' => $this->donation->church?->name]))
            ->line(__('Category: :category', ['category' => $this->donation->category_label]).($this->donation->campaign ? ' — '.$this->donation->campaign->title : ''))
            ->line(__('Reference: :ref', ['ref' => $this->donation->provider_ref ?: $this->donation->reference]))
            ->action(__('View your giving history'), route('giving.index'))
            ->line(__('“God loves a cheerful giver.” — 2 Corinthians 9:7'))
            ->attach($receipts->path($this->donation), ['as' => $receipts->filename($this->donation), 'mime' => 'application/pdf']);
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
