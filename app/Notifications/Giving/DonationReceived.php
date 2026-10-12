<?php

namespace App\Notifications\Giving;

use App\Models\Donation;
use App\Notifications\ChurchNotification;

/**
 * Alerts the church finance team about a new gift.
 */
class DonationReceived extends ChurchNotification
{
    public string $category = 'giving';

    public function __construct(public Donation $donation)
    {
        $this->churchId = $donation->church_id;
        $this->forceChannels = ['database'];
    }

    public function title(): string
    {
        return __('New gift: :amount (:category)', ['amount' => ff_money($this->donation->amount, $this->donation->currency), 'category' => $this->donation->category_label]);
    }

    public function body(): ?string
    {
        return __('From :name via :method', ['name' => $this->donation->display_name, 'method' => $this->donation->method_label]);
    }

    public function url(): string
    {
        return route('church-admin.giving.transactions', [$this->donation->church, 'q' => $this->donation->reference]);
    }

    public function icon(): string
    {
        return 'bi-cash-coin';
    }

    public function churchName(): ?string
    {
        return $this->donation->church?->name;
    }
}
