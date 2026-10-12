<?php

namespace App\Notifications\Events;

use App\Models\Event;
use App\Notifications\ChurchNotification;

class EventReminder extends ChurchNotification
{
    public string $category = 'event';

    public function __construct(public Event $event, public string $window = '24h')
    {
        $this->churchId = $event->church_id;
    }

    public function title(): string
    {
        return $this->window === '1h'
            ? __('Starting in 1 hour: :title', ['title' => $this->event->title])
            : __('Tomorrow: :title', ['title' => $this->event->title]);
    }

    public function body(): ?string
    {
        return $this->event->starts_at->format('D j M, g:i A').($this->event->location_name ? ' · '.$this->event->location_name : '');
    }

    public function url(): string
    {
        return route('events.show', $this->event);
    }

    public function icon(): string
    {
        return 'bi-alarm';
    }

    public function churchName(): ?string
    {
        return $this->event->church?->name;
    }
}
