<?php

namespace App\Services;

use App\Http\Resources\SermonResource;
use App\Models\Sermon;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * What the app's Sermons page shows above the full list, in one go: the banner (a few sermons, each by a different preacher, the ones with a real
 * picture first), the preachers of the churches the member follows, the newest sermons, the most listened ones and the series. Everything is limited
 * to what this member may see (public sermons, and members-only sermons of the churches they follow).
 */
class SermonBrowser
{
    private const BANNER = 6;

    /** @return array<string, mixed> */
    public function sections(User $user, Request $request): array
    {
        $memberIds = $user->memberChurchIds();
        $visible = fn (): Builder => Sermon::query()->published()->visibleTo($memberIds)->whereIn('church_id', $memberIds ?: [0]);
        $with = ['church.media', 'media', 'series'];
        $resolve = fn (Collection $sermons): array => SermonResource::collection($sermons)->resolve($request);

        $latest = $visible()->with($with)->latest('published_at')->take(10)->get();
        $popular = $visible()->with($with)->where('listens_count', '>', 0)->orderByDesc('listens_count')->latest('published_at')->take(8)->get();

        return [
            'featured' => $resolve($this->banner($visible()->with($with), $latest)),
            'preachers' => $this->preachers($visible()),
            'latest' => $resolve($latest),
            'popular' => $resolve($popular),
            'series' => $this->series($visible(), $with),
            'total' => $visible()->count(),
        ];
    }

    /**
     * The banner: sermons by different preachers, the ones with a real picture first, then the most listened, then the newest. When fewer than three
     * preachers are named, the newest sermons fill it up to three slides.
     *
     * @return Collection<int, Sermon>
     */
    protected function banner(Builder $query, Collection $latest): Collection
    {
        $candidates = $query->orderByDesc('listens_count')->latest('published_at')->take(40)->get();
        [$withPhoto, $plain] = $candidates->filter(fn (Sermon $s) => trim((string) $s->preacher) !== '')->partition(fn (Sermon $s) => $s->thumbnail_url !== null);
        $named = $withPhoto->concat($plain)->unique(fn (Sermon $s) => mb_strtolower(trim((string) $s->preacher)))->take(self::BANNER)->values();
        $fill = $latest->reject(fn (Sermon $s) => $named->contains('id', $s->id))->take(max(0, 3 - $named->count()));

        return $named->concat($fill)->values();
    }

    /** @return list<array{name: string, sermons: int, latest_at: string|null}> */
    protected function preachers(Builder $query): array
    {
        return $query->whereNotNull('preacher')->where('preacher', '!=', '')
            ->selectRaw('preacher, count(*) as sermons, max(published_at) as latest_at')
            ->groupBy('preacher')->orderByDesc('sermons')->orderByDesc('latest_at')->take(14)->get()
            ->map(fn ($row) => ['name' => (string) $row->preacher, 'sermons' => (int) $row->sermons, 'latest_at' => $row->latest_at ? (string) $row->latest_at : null])
            ->values()->all();
    }

    /**
     * @param  list<string>  $with
     * @return list<array{id: int, title: string, sermons: int, cover_url: string|null, church: string|null}>
     */
    protected function series(Builder $query, array $with): array
    {
        $counts = (clone $query)->whereNotNull('sermon_series_id')->selectRaw('sermon_series_id, count(*) as n')->groupBy('sermon_series_id')->pluck('n', 'sermon_series_id');
        if ($counts->isEmpty()) {
            return [];
        }

        return $query->with($with)->whereIn('sermon_series_id', $counts->keys())->latest('published_at')->take(80)->get()
            ->groupBy('sermon_series_id')
            ->map(fn (Collection $group) => [
                'id' => (int) $group->first()->sermon_series_id,
                'title' => (string) $group->first()->series?->title,
                'sermons' => (int) $counts[$group->first()->sermon_series_id],
                'cover_url' => $group->first()->picture_url,
                'church' => $group->first()->church?->name,
            ])
            ->filter(fn (array $s) => $s['title'] !== '')->take(8)->values()->all();
    }
}
