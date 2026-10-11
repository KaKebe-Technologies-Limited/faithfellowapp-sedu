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
        ];
    }
}
