<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Connection;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserBlock;
use App\Notifications\Social\ConnectionAccepted;
use App\Notifications\Social\ConnectionRequested;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Meeting other believers: who can be found, connection requests, blocking.
 *
 * The rules that keep it safe:
 *  - only adults who have given their date of birth can be found or write to anyone;
 *  - a member is found by their churches' members when they opted in to the member directory, and by every
 *    other adult on the app only when they chose "open to connect";
 *  - blocking works both ways and removes the connection;
 *  - messages only flow between accepted connections (see MessageController).
 */
class PeopleService
{
    public const MAX_PENDING = 25;

    /** Age brackets shown instead of an exact birthday. */
    public const AGE_BRACKETS = [[18, 24, '18–24'], [25, 34, '25–34'], [35, 44, '35–44'], [45, 54, '45–54'], [55, 200, '55+']];

    /** @param  array<string, mixed>  $filters  scope (church|everyone), q, country, city, gender, interest, age_min, age_max, church */
    public function discover(User $viewer, array $filters = []): Builder
    {
        $mine = $viewer->memberChurchIds() ?: [0];
        $church = isset($filters['church']) && in_array((int) $filters['church'], $mine, true) ? [(int) $filters['church']] : $mine;
        $sameChurch = fn (Builder $q) => $q->whereHas('subscriptions', fn ($s) => $s->whereIn('church_id', $church)->where('status', 'active'));
        $nearby = ($filters['scope'] ?? 'church') === 'nearby';
        $everyone = (in_array($filters['scope'] ?? 'church', ['everyone', 'nearby'], true)) && ! isset($filters['church']);

        $query = User::query()->with('media')
            ->where('status', 'active')->whereKeyNot($viewer->id)->whereNotIn('id', UserBlock::idsAround($viewer->id) ?: [0])
            ->whereNotNull('date_of_birth')->where('date_of_birth', '<=', now()->subYears(18)->toDateString())
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('directory_opt_in', true)->where($sameChurch))
                ->when($everyone, fn ($w) => $w->orWhere('open_to_connect', true)))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where('name', 'like', '%'.$v.'%'))
            ->when($filters['country'] ?? null, fn ($q, $v) => $q->where('country', strtoupper((string) $v)))
            ->when($filters['city'] ?? null, fn ($q, $v) => $q->where('city', 'like', $v))
            ->when($filters['gender'] ?? null, fn ($q, $v) => $q->where('gender', $v))
            ->when($filters['interest'] ?? null, fn ($q, $v) => $q->whereJsonContains('interests', $v))
            ->when($filters['age_min'] ?? null, fn ($q, $v) => $q->where('date_of_birth', '<=', now()->subYears((int) $v)->toDateString()))
            ->when($filters['age_max'] ?? null, fn ($q, $v) => $q->where('date_of_birth', '>', now()->subYears((int) $v + 1)->toDateString()));

        return $nearby ? $this->near($query, $viewer, (int) ($filters['radius'] ?? 50)) : $query->orderBy('name');
    }

    /**
     * "For you": the people the viewer may find (the same rules as everyone else), ranked by what they share: faith interests,
     * podcasts they follow, hymns they love, books of the Bible they highlight, the same church, a similar age, the same town,
     * and living close by. Each person says why they were suggested. A person with nothing in common still follows, so a new
     * member never sees an empty list.
     *
     * @param  array<string, mixed>  $filters
     */
    public function recommended(User $viewer, array $filters = [], int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $pool = $this->discover($viewer, [...$filters, 'scope' => 'everyone'])->limit(1000)->get();
        $ids = $pool->pluck('id')->all();
        $count = fn ($query) => $query->groupBy('user_id')->pluck('c', 'user_id');

        $myPodcasts = DB::table('podcast_follows')->where('user_id', $viewer->id)->pluck('podcast_id');
        $podcasts = $myPodcasts->isEmpty() ? collect() : $count(DB::table('podcast_follows')->whereIn('user_id', $ids)->whereIn('podcast_id', $myPodcasts)->selectRaw('user_id, COUNT(*) c'));
        $myHymns = DB::table('hymn_favorites')->where('user_id', $viewer->id)->pluck('hymn_id');
        $hymns = $myHymns->isEmpty() ? collect() : $count(DB::table('hymn_favorites')->whereIn('user_id', $ids)->whereIn('hymn_id', $myHymns)->selectRaw('user_id, COUNT(*) c'));
        $myBooks = DB::table('bible_highlights')->where('user_id', $viewer->id)->distinct()->pluck('book');
        $books = $myBooks->isEmpty() ? collect() : $count(DB::table('bible_highlights')->whereIn('user_id', $ids)->whereIn('book', $myBooks)->selectRaw('user_id, COUNT(DISTINCT book) c'));
        $mine = $viewer->memberChurchIds();
        $churches = $mine === [] ? collect() : $count(Subscription::query()->whereIn('user_id', $ids)->whereIn('church_id', $mine)->where('status', 'active')->selectRaw('user_id, COUNT(*) c')->toBase());
        $myInterests = collect($viewer->interests ?? [])->map(fn ($i) => mb_strtolower((string) $i));
        $myAge = $viewer->date_of_birth?->age;

        $ranked = $pool->map(function (User $other) use ($viewer, $podcasts, $hymns, $books, $churches, $myInterests, $myAge) {
            $score = 0;
            $why = [];

            $interests = collect($other->interests ?? [])->filter(fn ($i) => $myInterests->contains(mb_strtolower((string) $i)))->values();
            if ($interests->isNotEmpty()) {
                $score += min(5, $interests->count()) * 3;
                $why[] = __('Both like :things', ['things' => $interests->take(2)->implode(' & ')]);
            }
            if (($churches[$other->id] ?? 0) > 0) {
                $score += 4;
                $why[] = __('Same church');
            }
            if (($podcasts[$other->id] ?? 0) > 0) {
                $score += min(3, (int) $podcasts[$other->id]) * 2;
                $why[] = __('Follow the same podcasts');
            }
            if (($books[$other->id] ?? 0) > 0) {
                $score += min(3, (int) $books[$other->id]);
                $why[] = __('Read the same scriptures');
            }
            if (($hymns[$other->id] ?? 0) > 0) {
                $score += min(3, (int) $hymns[$other->id]);
                $why[] = __('Love the same hymns');
            }
            if ($myAge !== null && $other->date_of_birth) {
                $gap = abs($myAge - $other->date_of_birth->age);
                if ($gap <= 5) {
                    $score += 2;
                    $why[] = __('Similar age');
                } elseif ($gap <= 10) {
                    $score += 1;
                }
            }
            if ($viewer->city && $other->city && mb_strtolower($viewer->city) === mb_strtolower($other->city)) {
                $score += 2;
                $why[] = __('Same town');
            } elseif ($viewer->country && $viewer->country === $other->country) {
                $score += 1;
            }
            if ($viewer->latitude !== null && $other->latitude !== null && $this->kilometres($viewer, $other) <= 25) {
                $score += 2;
                $why[] = __('Lives near you');
            }

            $other->setAttribute('match_score', $score);
            $other->setAttribute('match_reasons', array_values(array_unique($why)));

            return $other;
        })->sortBy([['match_score', 'desc'], ['name', 'asc']])->values();

        return new LengthAwarePaginator($ranked->forPage($page, $perPage)->values(), $ranked->count(), $perPage, $page);
    }

    /** Straight-line kilometres between two members who both shared a place. */
    protected function kilometres(User $a, User $b): float
    {
        [$lat1, $lat2, $lng1, $lng2] = [deg2rad((float) $a->latitude), deg2rad((float) $b->latitude), deg2rad((float) $a->longitude), deg2rad((float) $b->longitude)];

        return 6371 * acos(min(1, cos($lat1) * cos($lat2) * cos($lng2 - $lng1) + sin($lat1) * sin($lat2)));
    }

    /** Only people who shared a place within `$radius` km of the viewer's, nearest first (nobody if the viewer shared none). */
    protected function near(Builder $query, User $viewer, int $radius): Builder
    {
        if ($viewer->latitude === null) {
            return $query->whereRaw('1 = 0');
        }
        $lat = (float) $viewer->latitude;
        $lng = (float) $viewer->longitude;
        $box = $radius / 111.0; // one degree of latitude is about 111 km
        $distance = '(6371 * acos(least(1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))';

        return $query->whereNotNull('latitude')->whereBetween('latitude', [$lat - $box, $lat + $box])
            ->whereRaw("{$distance} <= ?", [$lat, $lng, $lat, $radius])
            ->orderByRaw($distance, [$lat, $lng, $lat])->orderBy('name');
    }

    /** About how far apart two members are, as a rough label ("About 10 km away"), or null when either did not share a place. */
    public function distanceLabel(User $viewer, User $other): ?string
    {
        if ($viewer->latitude === null || $other->latitude === null) {
            return null;
        }
        $km = $this->kilometres($viewer, $other);

        return match (true) {
            $km < 5 => __('Less than 5 km away'),
            $km <= 50 => __('About :km km away', ['km' => (int) (round($km / 5) * 5)]),
            $km <= 200 => __('About :km km away', ['km' => (int) (round($km / 10) * 10)]),
            default => __('More than 200 km away'),
        };
    }

    /** May the viewer see this person's profile at all? (found by the rules above, or already in touch) */
    public function canSee(User $viewer, User $other): bool
    {
        if ($viewer->id === $other->id) {
            return true;
        }
        if (in_array($other->id, UserBlock::idsAround($viewer->id), true)) {
            return false;
        }
        if (Connection::between($viewer->id, $other->id)->exists()) {
            return true;
        }

        return $this->discover($viewer, ['scope' => 'everyone'])->whereKey($other->id)->exists();
    }

    public function isAdult(User $user): bool
    {
        return $user->date_of_birth !== null && $user->date_of_birth->lte(now()->subYears(18));
    }

    /** "none" | "pending_sent" | "pending_received" | "connected" */
    public function relation(User $viewer, User $other): string
    {
        $c = Connection::between($viewer->id, $other->id)->first();
        if (! $c || $c->status === 'declined') {
            return 'none';
        }

        return $c->status === 'accepted' ? 'connected' : ($c->requester_id === $viewer->id ? 'pending_sent' : 'pending_received');
    }

    public function ageBracket(?User $user): ?string
    {
        if (! $user?->date_of_birth) {
            return null;
        }
        $age = (int) $user->date_of_birth->diffInYears(now());
        foreach (self::AGE_BRACKETS as [$from, $to, $label]) {
            if ($age >= $from && $age <= $to) {
                return $label;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function person(User $viewer, User $other): array
    {
        $shared = Church::whereIn('id', array_intersect($viewer->memberChurchIds(), $other->subscribedChurchIds()))->orderBy('name')->get(['id', 'name', 'slug']);

        return [
            'id' => $other->id,
            'name' => $other->name,
            'avatar_url' => $other->avatar_url,
            'bio' => $other->bio,
            'city' => $other->city,
            'country' => $other->country,
            'gender' => $other->gender,
            'age_range' => $this->ageBracket($other),
            'interests' => $other->interests ?? [],
            'distance_label' => $this->distanceLabel($viewer, $other),
            'shared_churches' => $shared->map(fn ($c) => ['id' => $c->id, 'slug' => $c->slug, 'name' => $c->name])->values()->all(),
            'relation' => $this->relation($viewer, $other),
        ];
    }

    public function request(User $from, User $to, ?string $message = null): Connection
    {
        $fail = fn (string $text) => throw ValidationException::withMessages(['user' => $text]);

        if (! $this->isAdult($from)) {
            $fail(__('Add your date of birth in your profile first — connecting is for members aged 18 and over.'));
        }
        if (! $this->isAdult($to) || ! $this->canSee($from, $to)) {
            $fail(__('This person cannot be reached.'));
        }

        $existing = Connection::between($from->id, $to->id)->first();
        if ($existing?->status === 'accepted') {
            return $existing;
        }
        // They already asked you: asking back means yes.
        if ($existing && $existing->status === 'pending' && $existing->requester_id === $to->id) {
            return $this->respond($from, $existing, true);
        }
        if ($existing && $existing->status === 'pending') {
            return $existing;
        }
        if (Connection::where('requester_id', $from->id)->where('status', 'pending')->count() >= self::MAX_PENDING) {
            $fail(__('You have many requests waiting. Wait for some to be answered first.'));
        }

        $connection = $existing ?? new Connection(['requester_id' => $from->id, 'addressee_id' => $to->id]);
        $connection->fill(['requester_id' => $from->id, 'addressee_id' => $to->id, 'status' => 'pending', 'message' => $message ? mb_substr(strip_tags($message), 0, 280) : null, 'responded_at' => null])->save();
        $to->notify(new ConnectionRequested($from, $connection));
        app(DevicePushService::class)->toUser($to, 'social', [
            'title' => __(':name wants to connect with you', ['name' => $from->name]),
            'body' => $connection->message,
            'data' => ['kind' => 'connection_request', 'ref' => $from->id],
            'channelId' => 'messages',
        ]);

        return $connection;
    }

    public function respond(User $user, Connection $connection, bool $accept): Connection
    {
        abort_unless($connection->addressee_id === $user->id && $connection->status === 'pending', 404);
        $connection->update(['status' => $accept ? 'accepted' : 'declined', 'responded_at' => now()]);
        if ($accept) {
            $connection->requester->notify(new ConnectionAccepted($user));
            app(DevicePushService::class)->toUser($connection->requester, 'social', [
                'title' => __(':name accepted your request', ['name' => $user->name]),
                'body' => __('You can now send each other messages.'),
                'data' => ['kind' => 'connection_accepted', 'ref' => $user->id],
                'channelId' => 'messages',
            ]);
        }

        return $connection;
    }

    public function disconnect(User $user, User $other): void
    {
        Connection::between($user->id, $other->id)->delete();
    }

    public function block(User $user, User $other): void
    {
        abort_if($user->id === $other->id, 422);
        UserBlock::firstOrCreate(['user_id' => $user->id, 'blocked_id' => $other->id]);
        $this->disconnect($user, $other);
    }

    public function unblock(User $user, User $other): void
    {
        UserBlock::where('user_id', $user->id)->where('blocked_id', $other->id)->delete();
    }

    /** May these two members message each other right now? */
    public function mayMessage(User $a, User $b): bool
    {
        return ! in_array($b->id, UserBlock::idsAround($a->id), true)
            && Connection::between($a->id, $b->id)->where('status', 'accepted')->exists();
    }
}
