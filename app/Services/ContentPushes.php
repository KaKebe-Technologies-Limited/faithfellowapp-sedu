<?php

namespace App\Services;

use App\Models\Podcast;
use App\Models\PodcastEpisode;
use App\Models\RadioStation;
use App\Models\RadioTrack;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "New" pushes that pop up on members' phones when something fresh arrives: a new podcast episode, a newly approved podcast, a new teaching on
 * the Podcast radio. Members can switch them off under Notification settings, "New podcasts".
 */
class ContentPushes
{
    /** An episode older than this is not news (a feed that is read for the first time, or an episode dated in the past). */
    private const FRESH_DAYS = 7;

    public function __construct(protected PushBroadcaster $push) {}

    /**
     * A new episode: to the people who follow the podcast, or to everyone when it is the platform's own (the super admin's).
     * Returns how many people it was sent to.
     */
    public function episode(PodcastEpisode $episode): int
    {
        $podcast = $episode->podcast;
        if (! $podcast?->isListed() || $episode->status !== 'published' || ! $episode->published_at || $episode->published_at->isFuture() || $episode->published_at->lt(now()->subDays(self::FRESH_DAYS))) {
            return 0;
        }
        if (! Cache::add("podcast-episode-pushed:{$episode->id}", 1, now()->addDays(self::FRESH_DAYS))) {
            return 0;
        }

        $message = [
            'title' => __('🎙️ New episode: :podcast', ['podcast' => Str::limit($podcast->name, 60)]),
            'body' => Str::limit($episode->title, 140),
            'data' => ['kind' => 'podcast', 'ref' => $podcast->id, 'url' => '/podcast-studio/'.$podcast->id],
            'channelId' => 'default',
        ];

        return $podcast->owner?->isSuperAdmin()
            ? $this->push->toEveryone('podcast', $message, [$podcast->user_id])
            : $this->push->toUsers($podcast->followers()->pluck('users.id'), 'podcast', $message, [$podcast->user_id]);
    }

    /** A podcast that has just been approved for the first time is announced to everyone. */
    public function podcast(Podcast $podcast): int
    {
        if (! Cache::add("podcast-pushed:{$podcast->id}", 1, now()->addDays(30))) {
            return 0;
        }

        return $this->push->toEveryone('podcast', [
            'title' => __('🎙️ A new podcast'),
            'body' => Str::limit($podcast->name.($podcast->host_name ? ' — '.$podcast->host_name : ''), 140),
            'data' => ['kind' => 'podcast', 'ref' => $podcast->id, 'url' => '/podcast-studio/'.$podcast->id],
            'channelId' => 'default',
        ], [$podcast->user_id]);
    }

    /** A new teaching on the Podcast radio. A batch of uploads makes one push (the first), not one for every file. */
    public function radioTrack(RadioTrack $track): int
    {
        if (! $track->station_id || ! RadioStation::ready() || $track->station?->slug !== RadioStation::PODCAST || ! Cache::add('podcast-radio-pushed', 1, now()->addMinutes(10))) {
            return 0;
        }

        return $this->push->toEveryone('podcast', [
            'title' => __('🎙️ New on the Podcast radio'),
            'body' => Str::limit($track->title.($track->artist ? ' — '.$track->artist : ''), 140),
            'data' => ['kind' => 'radio', 'url' => '/radio'],
            'channelId' => 'default',
        ], array_filter([$track->uploaded_by]));
    }
}
