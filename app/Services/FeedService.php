<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Devotional;
use App\Models\Post;
use App\Models\Sermon;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the member home feed from every church the user follows.
 *
 * Each source returns recent items (newest first) as FeedItem arrays:
 *   ['type' => 'post', 'at' => Carbon, 'church' => Church, 'model' => Model]
 * Items are merged, sorted by date and cut to the page size. Paging uses a
 * "before" timestamp cursor so new posts don't shift pages.
 */
class FeedService
{
    public const PAGE_SIZE = 15;

    protected ?User $user = null;

    /**
     * @param  array<int, int>|null  $churchIds  limit to these churches (null = all followed)
     * @return array{items: Collection<int, array<string, mixed>>, next: ?string}
     */
    public function forUser(User $user, ?array $churchIds = null, ?string $before = null): array
    {
        $followed = $user->memberChurchIds();
        $churchIds = $churchIds === null ? $followed : array_values(array_intersect($churchIds, $followed));

        if ($churchIds === []) {
            return ['items' => collect(), 'next' => null];
        }

        $cursor = $before ? Carbon::parse($before) : now();

        $this->user = $user;

        $items = collect($this->sources())
            ->flatMap(fn (callable $source) => $source($churchIds, $cursor, self::PAGE_SIZE + 1))
            ->sortByDesc(fn ($item) => $item['at']->timestamp)
            ->values();

        $page = $items->take(self::PAGE_SIZE);
        $next = $items->count() > self::PAGE_SIZE ? $page->last()['at']->toIso8601String() : null;

        return ['items' => $page, 'next' => $next];
    }

    /**
     * Content sources. Later modules (sermons, events, campaigns…) add theirs here.
     *
     * @return array<string, callable(array<int, int>, Carbon, int): Collection<int, array<string, mixed>>>
     */
    protected function sources(): array
    {
        return [
            'posts' => fn (array $churchIds, Carbon $before, int $limit) => Post::query()
                ->with(['church.media', 'author.media', 'media'])
                ->forChurches($churchIds)
                ->published()
                ->where('published_at', '<', $before)
                ->latest('published_at')
                ->take($limit)
                ->get()
                ->map(fn (Post $post) => ['type' => 'post', 'at' => $post->published_at, 'church' => $post->church, 'model' => $post]),

            'sermons' => fn (array $churchIds, Carbon $before, int $limit) => Sermon::query()
                ->with(['church.media', 'media', 'series'])
                ->forChurches($churchIds)->published()
                ->where('published_at', '<', $before)
                ->latest('published_at')->take($limit)->get()
                ->map(fn (Sermon $s) => ['type' => 'sermon', 'at' => $s->published_at, 'church' => $s->church, 'model' => $s]),

            'devotionals' => fn (array $churchIds, Carbon $before, int $limit) => Devotional::query()
                ->with(['church.media'])
                ->forChurches($churchIds)->published()
                ->whereDate('publish_date', '<=', $before)
                ->latest('publish_date')->take($limit)->get()
                // Devotionals sort at 05:00 on their date (or just before the cursor if that's still ahead).
                ->map(fn (Devotional $d) => ['type' => 'devotional', 'at' => $d->publish_date->copy()->setTime(5, 0)->min($before->copy()->subSecond()), 'church' => $d->church, 'model' => $d]),

            'announcements' => fn (array $churchIds, Carbon $before, int $limit) => Announcement::query()
                ->with(['church.media', 'media'])
                ->forChurches($churchIds)->current()
                ->forAudience($churchIds, $this->user?->staffMemberships()->where('status', 'active')->pluck('church_id')->all() ?? [], $this->groupIds())
                ->where('published_at', '<', $before)
                ->latest('published_at')->take($limit)->get()
                ->map(fn (Announcement $a) => ['type' => 'announcement', 'at' => $a->published_at, 'church' => $a->church, 'model' => $a]),
        ];
    }

    /**
     * Small groups the current user belongs to (for group-targeted announcements).
     *
     * @return array<int, int>
     */
    protected function groupIds(): array
    {
        if (! $this->user || ! Schema::hasTable('small_group_members')) {
            return [];
        }

        return \DB::table('small_group_members')->where('user_id', $this->user->id)->pluck('small_group_id')->map(fn ($id) => (int) $id)->all();
    }
}
