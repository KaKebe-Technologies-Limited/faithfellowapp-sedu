<?php

namespace App\Notifications\Content;

use App\Models\Post;
use App\Notifications\ChurchNotification;
use Illuminate\Support\Str;

/**
 * A new update (feed post) from a church the member follows.
 */
class NewChurchUpdate extends ChurchNotification
{
    public string $category = 'update';

    public function __construct(public Post $post)
    {
        $this->churchId = $post->church_id;
    }

    public function title(): string
    {
        return __('New update from :church', ['church' => $this->post->church?->name]);
    }

    public function body(): ?string
    {
        return Str::limit(strip_tags($this->post->body), 140);
    }

    public function url(): string
    {
        return route('dashboard', ['church' => $this->post->church?->slug]);
    }

    /** The app opens the update itself (with all its photos) instead of a website page. */
    public function toArray(object $notifiable): array
    {
        return [...parent::toArray($notifiable), 'kind' => 'post', 'ref' => $this->post->id];
    }

    public function toPush(object $notifiable): array
    {
        $push = parent::toPush($notifiable);
        $push['data'] = [...$push['data'], 'kind' => 'post', 'ref' => $this->post->id];

        return $push;
    }

    public function icon(): string
    {
        return 'bi-postcard-heart';
    }

    public function churchName(): ?string
    {
        return $this->post->church?->name;
    }
}
