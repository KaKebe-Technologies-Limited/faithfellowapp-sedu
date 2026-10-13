<?php

namespace App\Notifications\Podcasts;

use App\Models\Podcast;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Tells a podcaster what the review decided: approved (it is now in the app), not approved yet (with why), or hidden. */
class PodcastReviewed extends Notification
{
    use Queueable;

    public function __construct(public Podcast $podcast, public string $outcome, public ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => match ($this->outcome) {
                'approved' => __('“:name” is now in the FaithFellows app', ['name' => $this->podcast->name]),
                'rejected' => __('“:name” was not approved yet', ['name' => $this->podcast->name]),
                default => __('“:name” was hidden', ['name' => $this->podcast->name]),
            },
            'body' => $this->outcome === 'approved' ? __('Listeners can now find it and play your episodes.') : ($this->reason ?: __('Open your podcast to see what to change.')),
            'icon' => 'bi-mic',
            'url' => route('podcasts.show', $this->podcast),
            'kind' => 'podcast',
            'ref' => $this->podcast->id,
        ];
    }
}
