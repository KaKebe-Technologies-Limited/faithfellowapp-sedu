<?php

namespace App\Notifications\Events;

use App\Models\Event;
use App\Notifications\ChurchNotification;

class NewEventPublished extends ChurchNotification
{
    public string $category = 'event';

    public function __construct(public Event $event)
    {
        $this->churchId = $event->church_id;
    }

    public function title(): string
    {
        return __('New event: :title', ['title' => $this->event->title]);
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
        return 'bi-calendar-event';
    }

    public function churchName(): ?string
    {
        return $this->event->church?->name;
    }
}
