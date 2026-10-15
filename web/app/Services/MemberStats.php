<?php

namespace App\Services;

use App\Models\BibleHighlight;
use App\Models\Connection;
use App\Models\Donation;
use App\Models\MemberActivity;
use App\Models\Note;
use App\Models\PrayerInteraction;
use App\Models\QuizPlayer;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * "How am I doing?": a member's own figures, kept private. The app reports small pieces of activity (minutes with the Bible open,
 * minutes of radio and podcasts, scriptures and inspiring messages shared); this adds them up for the week or the month, measures
 * them against gentle goals for the progress rings, counts the streak of days, and says what is still missing from the profile.
 */
class MemberStats
{
    /** Gentle goals for one week: minutes with the Bible, minutes listening, things shared. */
    public const GOALS = ['bible' => 70, 'listening' => 60, 'sharing' => 5];

    /** The most one event can add, so a stuck timer cannot fill the day. */
    private const MAX_SECONDS_PER_EVENT = 3600;

    private const MAX_PER_DAY = ['seconds' => 43200, 'count' => 500];

    /**
     * Adds what the app reported. Unknown kinds, future days and days older than a week (a phone that was offline) are ignored.
     *
     * @param  array<int, array{kind?: string, seconds?: int, count?: int, day?: string}>  $events
     */
    public function record(User $user, array $events): int
    {
        $today = $this->today($user);
        $recorded = 0;

        foreach ($events as $event) {
            $kind = (string) ($event['kind'] ?? '');
            $unit = MemberActivity::KINDS[$kind] ?? null;
            if (! $unit) {
                continue;
            }

            $day = isset($event['day']) ? Carbon::parse($event['day'], $today->getTimezone())->startOfDay() : $today->copy();
            if ($day->gt($today) || $day->lt($today->copy()->subDays(7))) {
                continue;
            }

            $amount = $unit === 'seconds'
                ? max(0, min(self::MAX_SECONDS_PER_EVENT, (int) ($event['seconds'] ?? 0)))
                : max(0, min(50, (int) ($event['count'] ?? 1)));
            if ($amount === 0) {
                continue;
            }

            $row = MemberActivity::firstOrCreate(['user_id' => $user->id, 'day' => $day->toDateString(), 'kind' => $kind], ['seconds' => 0, 'count' => 0]);
            $row->{$unit === 'seconds' ? 'seconds' : 'count'} = min(self::MAX_PER_DAY[$unit], $row->{$unit === 'seconds' ? 'seconds' : 'count'} + $amount);
            $row->save();
            $recorded++;
        }

        return $recorded;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user, string $period = 'week'): array
    {
        $today = $this->today($user);
        [$from, $to] = match ($period) {
            'month' => [$today->copy()->startOfMonth(), $today->copy()],
            'all' => [Carbon::parse(MemberActivity::where('user_id', $user->id)->min('day') ?? $today->toDateString(), $today->getTimezone())->startOfDay(), $today->copy()],
            default => [$today->copy()->startOfWeek(), $today->copy()],
        };

        $sums = MemberActivity::where('user_id', $user->id)->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('kind, SUM(seconds) as seconds, SUM(count) as count')->groupBy('kind')->get()->keyBy('kind');
        $secs = fn (string $kind) => (int) ($sums[$kind]->seconds ?? 0);
        $count = fn (string $kind) => (int) ($sums[$kind]->count ?? 0);

        $readMin = intdiv($secs('read'), 60);
        $radioMin = intdiv($secs('radio'), 60);
        $podcastMin = intdiv($secs('podcast'), 60);
        $listenMin = intdiv($secs('radio') + $secs('podcast'), 60);
        $shares = $count('share_scripture') + $count('share_inspiration');

        // A month or "all time" is judged against the same weekly goals, scaled to the weeks in it.
        $weeks = max(1, (int) ceil(($from->diffInDays($to) + 1) / 7));
        $ring = fn (string $key, string $label, int $value, string $unit) => [
            'key' => $key, 'label' => $label, 'value' => $value, 'unit' => $unit, 'goal' => self::GOALS[$key] * $weeks,
            'percent' => (int) min(100, round($value / (self::GOALS[$key] * $weeks) * 100)),
        ];

        return [
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => [
                'read_minutes' => $readMin,
                'chapters' => $count('chapters'),
                'radio_minutes' => $radioMin,
                'podcast_minutes' => $podcastMin,
                'listen_minutes' => $listenMin,
                'shared_scriptures' => $count('share_scripture'),
                'shared_inspirations' => $count('share_inspiration'),
                'shares' => $shares,
                'opens' => $count('open'),
            ],
            'rings' => [
                $ring('bible', __('Bible'), $readMin, __('min')),
                $ring('listening', __('Listening'), $listenMin, __('min')),
                $ring('sharing', __('Sharing'), $shares, __('shared')),
            ],
            'week' => $this->lastSevenDays($user, $today),
            'streak' => $this->streak($user, $today),
            'other' => $this->other($user),
            'notes' => Note::ownedBy($user)->latest('updated_at')->take(3)->get()->map(fn (Note $n) => [
                'id' => $n->id, 'title' => $n->displayTitle(), 'body' => mb_substr(trim(strip_tags((string) $n->body)), 0, 120), 'reference' => $n->reference,
                'color_hex' => Note::COLORS[$n->color ?: 'paper'] ?? Note::COLORS['paper'], 'updated_at' => $n->updated_at?->toIso8601String(),
            ])->values(),
            'profile' => $this->profile($user),
        ];
    }

    /** What is still missing from the profile, and how far along it is. phone_missing is the one the giving screen cares about. */
    public function profile(User $user): array
    {
        $checks = [
            ['key' => 'phone', 'label' => __('Your phone number'), 'hint' => __('It fills in mobile money for you when you give.'), 'weight' => 20, 'done' => filled($user->phone)],
            ['key' => 'photo', 'label' => __('A profile photo'), 'hint' => __('So your church family knows you.'), 'weight' => 15, 'done' => filled($user->avatar_url)],
            ['key' => 'church', 'label' => __('Follow a church'), 'hint' => __('Get its sermons, events and prayer requests.'), 'weight' => 15, 'done' => $user->memberChurchIds() !== []],
            ['key' => 'birthday', 'label' => __('Your birthday'), 'hint' => __('Needed to meet other believers. Only an age range is shown.'), 'weight' => 15, 'done' => $user->date_of_birth !== null],
            ['key' => 'city', 'label' => __('Your town'), 'hint' => __('Helps you find people and churches near you.'), 'weight' => 10, 'done' => filled($user->city)],
            ['key' => 'bio', 'label' => __('A line about you'), 'hint' => __('Tell others about your faith journey.'), 'weight' => 10, 'done' => filled($user->bio)],
            ['key' => 'interests', 'label' => __('What you care about'), 'hint' => __('Worship, prayer, Bible study… so we can suggest people for you.'), 'weight' => 15, 'done' => ! empty($user->interests)],
        ];

        return [
            'percent' => (int) collect($checks)->where('done', true)->sum('weight'),
            'phone_missing' => blank($user->phone),
            'missing' => collect($checks)->where('done', false)->map(fn ($c) => ['key' => $c['key'], 'label' => $c['label'], 'hint' => $c['hint']])->values()->all(),
        ];
    }

    protected function today(User $user): Carbon
    {
        return Carbon::now($user->timezone ?: config('app.timezone'))->startOfDay();
    }

    /** @return array<int, array{day: string, label: string, read_minutes: int, listen_minutes: int, shares: int}> */
    protected function lastSevenDays(User $user, Carbon $today): array
    {
        $from = $today->copy()->subDays(6);
        $rows = MemberActivity::where('user_id', $user->id)->whereBetween('day', [$from->toDateString(), $today->toDateString()])->get();

        return collect(range(0, 6))->map(function (int $i) use ($from, $rows) {
            $day = $from->copy()->addDays($i);
            $of = $rows->filter(fn ($r) => $r->day->toDateString() === $day->toDateString());

            return [
                'day' => $day->toDateString(),
                'label' => $day->format('D'),
                'read_minutes' => intdiv((int) $of->where('kind', 'read')->sum('seconds'), 60),
                'listen_minutes' => intdiv((int) $of->whereIn('kind', ['radio', 'podcast'])->sum('seconds'), 60),
                'shares' => (int) $of->whereIn('kind', ['share_scripture', 'share_inspiration'])->sum('count'),
            ];
        })->all();
    }

    /** @return array{current: int, best: int} days in a row with any activity (today may still be to come) */
    protected function streak(User $user, Carbon $today): array
    {
        $days = MemberActivity::where('user_id', $user->id)->where('day', '>=', $today->copy()->subDays(400)->toDateString())->distinct()->orderByDesc('day')->pluck('day')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        if ($days === []) {
            return ['current' => 0, 'best' => 0];
        }

        $set = array_flip($days);
        $cursor = $today->copy();
        if (! isset($set[$cursor->toDateString()])) {
            $cursor->subDay(); // not opened yet today: the streak still stands until the day ends
        }
        $current = 0;
        while (isset($set[$cursor->toDateString()])) {
            $current++;
            $cursor->subDay();
        }

        $best = 0;
        $run = 0;
        $previous = null;
        foreach (array_reverse($days) as $day) {
            $run = $previous && Carbon::parse($previous)->addDay()->toDateString() === $day ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $day;
        }

        return ['current' => $current, 'best' => max($best, $current)];
    }

    /** @return array<string, mixed> what the member has done in the rest of the app */
    protected function other(User $user): array
    {
        $given = Donation::where('user_id', $user->id)->succeeded()->whereYear('paid_at', now()->year);

        return [
            'highlights' => BibleHighlight::where('user_id', $user->id)->count(),
            'notes' => Note::ownedBy($user)->count(),
            'games_played' => QuizPlayer::where('user_id', $user->id)->count(),
            'best_score' => (int) QuizPlayer::where('user_id', $user->id)->max('score'),
            'prayers' => PrayerInteraction::where('user_id', $user->id)->where('type', 'prayed')->count(),
            'given_year' => (float) (clone $given)->sum('amount'),
            'gifts_year' => (clone $given)->count(),
            'currency' => config('faithfellows.currency'),
            'churches' => count($user->memberChurchIds()),
            'connections' => Connection::involving($user->id)->where('status', 'accepted')->count(),
            'member_since' => $user->created_at?->toDateString(),
        ];
    }
}
