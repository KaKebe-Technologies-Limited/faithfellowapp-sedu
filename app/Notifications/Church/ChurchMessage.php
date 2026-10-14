<?php

namespace App\Notifications\Church;

use App\Models\ChurchBroadcast;
use App\Notifications\ChurchNotification;
use Illuminate\Support\Str;

/**
 * A message written in the church notification composer.
 *
 * Non-urgent: delivered on the channels the sender picked *that each member
 * also wants* for this category. Urgent: the picked channels are forced and
 * quiet hours / digest mode are skipped.
 */
class ChurchMessage extends ChurchNotification
{
    public function __construct(
        public string $heading,
        public string $message,
        public string $link,
        public string $church,
        string $category = 'announcement',
        array $channels = [],
        bool $urgent = false,
        ?int $broadcastId = null,
    ) {
        $this->category = $category;
        $this->urgent = $urgent;
        $this->broadcastId = $broadcastId;
        $this->onlyChannels = $urgent ? null : $channels;
        $this->forceChannels = $urgent ? $channels : [];
    }

    public static function fromBroadcast(ChurchBroadcast $broadcast): self
    {
        $broadcast->loadMissing('church');

        $notification = new self(
            $broadcast->title,
            $broadcast->body,
            $broadcast->url ?: route('churches.show', $broadcast->church),
            $broadcast->church->name,
            $broadcast->category,
            $broadcast->channels ?? [],
            $broadcast->is_urgent,
            $broadcast->id,
        );
        $notification->churchId = $broadcast->church_id;

        return $notification;
    }

    public function title(): string
    {
        return ($this->urgent ? '⚠️ ' : '').$this->heading;
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
        return $this->urgent ? 'bi-exclamation-triangle' : 'bi-megaphone';
    }

    public function churchName(): ?string
    {
        return $this->church;
    }

    public function toArray(object $notifiable): array
    {
        return [...parent::toArray($notifiable), 'broadcast_id' => $this->broadcastId, 'urgent' => $this->urgent];
    }

    /** SMS carries the message itself (members may not have data). */
    public function toSms(object $notifiable): string
    {
        return Str::limit($this->church.': '.$this->heading.' — '.strip_tags($this->message), 300);
    }
}
