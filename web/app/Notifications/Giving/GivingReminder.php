<?php

namespace App\Notifications\Giving;

use App\Models\Church;
use App\Notifications\ChurchNotification;

class GivingReminder extends ChurchNotification
{
    public string $category = 'giving';

    public function __construct(public Church $church, public string $message, public string $link)
    {
        $this->churchId = $church->id;
    }

    public function title(): string
    {
        return $this->message;
    }

    public function body(): ?string
    {
        return __('Tap to give to :church. Thank you for your faithfulness!', ['church' => $this->church->name]);
    }

    public function url(): string
    {
        return $this->link;
    }

    public function icon(): string
    {
        return 'bi-heart';
    }

    public function churchName(): ?string
    {
        return $this->church->name;
    }
}
