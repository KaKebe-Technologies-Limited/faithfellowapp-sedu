<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Subscription;
use App\Models\User;

/**
 * Builds the data shown on a church's public page and auto-generated website.
 * Each content module adds its section here so the page and the website
 * builder blocks share one data source.
 */
class ChurchPageService
{
    /**
     * @return array<string, mixed>
     */
    public function sections(Church $church, ?User $viewer = null): array
    {
        $isMember = $viewer?->isMemberOf($church) ?? false;

        return [
            'viewer' => $viewer,
            'isStaff' => $viewer?->isStaffOf($church) ?? false,
            'isMember' => $isMember,
            'subscription' => $viewer ? Subscription::where('user_id', $viewer->id)->where('church_id', $church->id)->first() : null,
            'subscriberCount' => $church->subscriptions()->where('status', 'active')->count(),
            'posts' => $church->posts()->with(['author.media', 'media'])->published()
                ->when(! $isMember, fn ($q) => $q->where('visibility', 'public'))
                ->orderByDesc('is_pinned')->latest('published_at')->take(5)->get(),
            'sermons' => $church->sermons()->with(['media', 'series'])->published()
                ->when(! $isMember, fn ($q) => $q->where('visibility', 'public'))
                ->latest('published_at')->take(12)->get()
                ->each(fn ($s) => $s->setRelation('church', $church)),
            'events' => $church->events()->with('media')->published()->upcoming()
                ->when(! $isMember, fn ($q) => $q->where('visibility', 'public'))
                ->orderBy('starts_at')->take(10)->get()
                ->each(fn ($e) => $e->setRelation('church', $church)),
            'campaigns' => $church->campaigns()->with('media')->whereIn('status', ['active'])
                ->when(! $isMember, fn ($q) => $q->where('visibility', 'public'))
                ->orderByDesc('is_featured')->latest()->take(6)->get()
                ->each(fn ($c) => $c->setRelation('church', $church)),
            'givingChannels' => $church->givingChannels()->where('is_active', true)->get(),
            'liveNow' => $church->liveStreams()->live()->latest('started_at')->first(),
            'nextLive' => $church->liveStreams()->where('status', 'scheduled')->where('scheduled_at', '>=', now())->orderBy('scheduled_at')->first(),
            'devotional' => $church->devotionals()->published()
                ->when(! $isMember, fn ($q) => $q->where('visibility', 'public'))
                ->latest('publish_date')->first(),
        ];
    }
}
