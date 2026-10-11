<?php

namespace App\Notifications\Content;

use App\Models\Announcement;
use App\Notifications\ChurchNotification;

class AnnouncementPublished extends ChurchNotification
{
    public string $category = 'announcement';

    public function __construct(public Announcement $announcement)
    {
        $this->churchId = $announcement->church_id;
        // Admins can push an announcement over extra channels (email / SMS / WhatsApp).
        $this->forceChannels = $announcement->channels ?? [];
    }

    public function title(): string
    {
        return ($this->announcement->priority === 'urgent' ? '⚠️ ' : '').$this->announcement->title;
    }

    public function body(): ?string
    {
        return $this->announcement->body;
    }

    public function url(): string
    {
        return route('announcements.show', $this->announcement);
    }

    public function icon(): string
    {
        return 'bi-megaphone';
    }

    public function churchName(): ?string
    {
        return $this->announcement->church?->name;
    }
}
