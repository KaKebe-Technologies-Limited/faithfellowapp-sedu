<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        ];
    }
}
