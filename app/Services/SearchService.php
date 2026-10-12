<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Church;
use App\Models\Devotional;
use App\Models\Event;
use App\Models\Sermon;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Search across content the member can see, and across a church for admins.
 * Uses LIKE queries (MySQL full-text / Meilisearch can replace this later).
 */
class SearchService
{
    /**
     * @return array<string, Collection<int, mixed>>
     */
    public function forMember(User $user, string $q): array
    {
        $memberIds = $user->memberChurchIds();
        $like = '%'.$q.'%';

        $results = [
            'churches' => Church::active()->with('media')->search($q)->take(6)->get(),
            'sermons' => Sermon::with(['church.media', 'media'])->published()->visibleTo($memberIds)
                ->whereHas('church', fn ($c) => $c->where('status', 'active'))->search($q)->latest('published_at')->take(10)->get(),
            'devotionals' => Devotional::with('church.media')->published()->visibleTo($memberIds)
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('scripture', 'like', $like)->orWhere('body', 'like', $like))
                ->latest('publish_date')->take(6)->get(),
            'announcements' => Announcement::with('church.media')->current()->forAudience($memberIds, $user->staffMemberships()->pluck('church_id')->all())
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('body', 'like', $like))
                ->latest('published_at')->take(6)->get(),
        ];

        foreach ($this->extraMemberSources() as $key => $source) {
            $results[$key] = $source($user, $q);
        }

        return $results;
    }

    /**
     * Additional searchable modules (events, campaigns…) register here.
     *
     * @return array<string, callable(User, string): Collection<int, mixed>>
     */
    protected function extraMemberSources(): array
    {
        return [
            'events' => fn (User $user, string $q) => Event::with('church.media')->published()->upcoming()
                ->visibleTo($user->memberChurchIds())
                ->whereHas('church', fn ($c) => $c->where('status', 'active'))
                ->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('location_name', 'like', "%{$q}%"))
                ->orderBy('starts_at')->take(6)->get(),
        ];
    }

    /**
     * Church admin search: members and content in one church.
     *
     * @return array<string, Collection<int, mixed>>
     */
    public function forChurch(Church $church, string $q): array
    {
        $like = '%'.$q.'%';

        return [
            'members' => User::with('media')->whereHas('subscriptions', fn ($s) => $s->where('church_id', $church->id))
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like))->take(10)->get(),
            'sermons' => $church->sermons()->search($q)->latest('published_at')->take(10)->get(),
            'devotionals' => $church->devotionals()->where('title', 'like', $like)->take(6)->get(),
            'announcements' => $church->announcements()->where('title', 'like', $like)->take(6)->get(),
        ];
    }
}
