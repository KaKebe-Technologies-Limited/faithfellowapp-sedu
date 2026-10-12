<?php

namespace App\Notifications\Care;

use App\Models\Church;
use App\Notifications\ChurchNotification;

/**
 * General-purpose member notification for community workflows
 * (testimony approved, counseling confirmed, rota assigned, group join…).
 */
class CommunityUpdate extends ChurchNotification
{
    public function __construct(
        public Church $church,
        public string $heading,
        public string $link,
        public ?string $message = null,
        public string $iconName = 'bi-bell',
        string $category = 'announcement',
        bool $email = false,
    ) {
        $this->churchId = $church->id;
        $this->category = $category;
        $this->forceChannels = $email ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return $this->heading;
    }

    public function body(): ?string
    {
        return $this->message;
    }

    public function url(): string
    {
        return $this->link;
    }

    public function icon(): string
    {
        return $this->iconName;
    }

    public function churchName(): ?string
    {
        return $this->church->name;
    }
}
