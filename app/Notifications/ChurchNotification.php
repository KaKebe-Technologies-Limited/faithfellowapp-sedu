<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Base class for notifications a church sends to its members.
 *
 * Channels are chosen per recipient by NotificationService (preferences per
 * church & category, quiet hours, digest mode and the fallback chain), so
 * subclasses only describe the message.
 */
abstract class ChurchNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public ?int $churchId = null;

    public string $category = 'announcement';

    /** Channels the sender insists on (e.g. urgent announcement by SMS). */
    public array $forceChannels = [];

    /** Limit delivery to these channels (the composer's picks); null = any the member wants. */
    public ?array $onlyChannels = null;

    /** Urgent messages skip quiet hours and digest mode. */
    public bool $urgent = false;

    /** Set when the message was composed in the church notification composer. */
    public ?int $broadcastId = null;

    abstract public function title(): string;

    abstract public function url(): string;

    public function body(): ?string
    {
        return null;
    }

    public function icon(): string
    {
        return 'bi-bell';
    }

    public function churchName(): ?string
    {
        return null;
    }

    public function via(object $notifiable): array
    {
        return app(NotificationService::class)->channelsFor($notifiable, $this);
    }

    /**
     * Quiet hours: when queued, push/SMS/WhatsApp wait until the window ends.
     */
    public function withDelay(object $notifiable, string $channel): ?\DateTimeInterface
    {
        return $notifiable instanceof User
            ? app(NotificationService::class)->delayFor($notifiable, $channel, $this)
            : null;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body() ? Str::limit(strip_tags($this->body()), 280) : null,
            'icon' => $this->icon(),
            'url' => $this->url(),
            'church' => $this->churchName(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name ?? '']));

        if ($this->body()) {
            $mail->line(Str::limit(strip_tags($this->body()), 600));
        }

        return $mail->action(__('Open in FaithFellows'), $this->url())
            ->line(__('You are receiving this because you follow :church. Manage alerts in your notification preferences.', ['church' => $this->churchName() ?? 'this church']));
    }

    /** Plain-text for SMS (≤ 160 chars where possible). */
    public function toSms(object $notifiable): string
    {
        return Str::limit(($this->churchName() ? $this->churchName().': ' : '').$this->title(), 120).' '.$this->url();
    }

    public function toWhatsapp(object $notifiable): string
    {
        return '*'.$this->title()."*\n".Str::limit(strip_tags((string) $this->body()), 600)."\n".$this->url();
    }

    /**
     * The message for the phone app. `data` is what the app opens when the member taps it.
     *
     * @return array<string, mixed>
     */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => Str::limit(strip_tags((string) $this->body()), 160),
            'subtitle' => $this->churchName(),
            'data' => array_filter(['url' => $this->url(), 'category' => $this->category, 'church_id' => $this->churchId]),
            'channelId' => $this->urgent ? 'urgent' : ($this->category === 'live' ? 'live' : 'default'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => Str::limit(strip_tags((string) $this->body()), 140),
            'url' => $this->url(),
            'tag' => $this->category.'-'.$this->churchId,
        ];
    }
}
