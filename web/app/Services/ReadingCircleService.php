<?php

namespace App\Services;

use App\Models\ReadingCircle;
use App\Models\ReadingCircleEntry;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanProgress;
use App\Models\User;
use App\Notifications\Reading\ReadingCircleUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Reading circles: pair up (max 4) with a code, get reminded together,
 * tell each other when you've read, share private summaries.
 */
class ReadingCircleService
{
    /**
     * @param  array{name: string, reading_time: string, reading_days: array<int, int>}  $data
     */
    public function create(User $owner, ReadingPlan $plan, array $data): ReadingCircle
    {
        return DB::transaction(function () use ($owner, $plan, $data) {
            $circle = ReadingCircle::create([
                'church_id' => $plan->church_id,
                'reading_plan_id' => $plan->id,
                'created_by' => $owner->id,
                'name' => $data['name'],
                'code' => $this->uniqueCode(),
                'reading_time' => $data['reading_time'],
                'reading_days' => array_values(array_map('intval', $data['reading_days'])),
                'timezone' => $owner->timezone ?: config('app.timezone'),
                'max_members' => (int) config('faithfellows.limits.reading_circle_members', 4),
            ]);
            $circle->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'joined_at' => now()]);
            $this->startPlan($owner, $plan);

            return $circle;
        });
    }

    public function join(User $user, string $code): ReadingCircle
    {
        $circle = ReadingCircle::with('plan')->where('code', strtoupper(trim($code)))->first();

        $fail = fn (string $message) => throw ValidationException::withMessages(['code' => $message]);
        if (! $circle || ! $circle->plan) {
            $fail(__('No reading circle has that code. Check it with your partner.'));
        }
        if ($circle->hasMember($user)) {
            return $circle;
        }
        if (! $user->isMemberOf($circle->church_id)) {
            $fail(__('Reading circles are for members of the same church. Follow :church first.', ['church' => $circle->church->name]));
        }
        if ($circle->isFull()) {
            $fail(__('This circle already has :max members.', ['max' => $circle->max_members]));
        }

        $circle->memberships()->create(['user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);
        $this->startPlan($user, $circle->plan);

        Notification::send($this->others($circle, $user), new ReadingCircleUpdate($circle, 'joined', $user->first_name));

        return $circle;
    }

    public function leave(User $user, ReadingCircle $circle): void
    {
        DB::transaction(function () use ($user, $circle) {
            $membership = $circle->memberships()->where('user_id', $user->id)->first();
            $membership?->delete();

            $next = $circle->memberships()->orderBy('joined_at')->first();
            if (! $next) {
                $circle->delete();
            } elseif ($membership?->role === 'owner') {
                $next->update(['role' => 'owner']);
            }
        });
    }

    /**
     * Mark a day read (with an optional summary) and tell the partners.
     */
    public function complete(User $user, ReadingCircle $circle, int $day, ?string $summary): ReadingCircleEntry
    {
        $entry = ReadingCircleEntry::firstOrNew(['reading_circle_id' => $circle->id, 'user_id' => $user->id, 'day' => $day]);
        $firstTime = ! $entry->completed_at;
        $entry->fill(['summary' => $summary !== null ? trim(strip_tags($summary)) : $entry->summary, 'completed_at' => $entry->completed_at ?? now()])->save();

        $progress = $this->startPlan($user, $circle->plan);
        $progress->setRelation('plan', $circle->plan);
        if (! in_array($day, $progress->completed_days ?? [], true)) {
            $progress->markRead($day);
        }

        if ($firstTime) {
            $passages = $circle->plan->entryForDay($day)['passages'] ?? '';
            Notification::send($this->others($circle, $user), new ReadingCircleUpdate($circle, 'finished', $user->first_name, $day, $passages));

            $memberCount = $circle->memberships()->count();
            $finished = ReadingCircleEntry::where('reading_circle_id', $circle->id)->where('day', $day)->whereNotNull('completed_at')->count();
            if ($memberCount > 1 && $finished >= $memberCount) {
                Notification::send($circle->members()->get(), new ReadingCircleUpdate($circle, 'all_done', null, $day, $passages));
            }
        }

        return $entry;
    }

    /**
     * Reminders at the agreed time (run every minute). A circle is reminded at
     * most once per day, within 10 minutes after its reading time.
     */
    public function sendReminders(): int
    {
        $sent = 0;
        // Circles keep their own timezone, so the due check happens per circle.
        ReadingCircle::with('plan')->each(function (ReadingCircle $circle) use (&$sent) {
            $local = now($circle->timezone ?: config('app.timezone'));
            $due = $local->copy()->setTimeFromTimeString($circle->reading_time);

            if (! in_array($local->dayOfWeek, $circle->reading_days ?? [], true)
                || $local->lt($due) || $local->gt($due->copy()->addMinutes(10))
                || $circle->last_reminded_on?->isSameDay($local)) {
                return;
            }

            $day = $circle->currentDay();
            $passages = $circle->plan?->entryForDay($day)['passages'] ?? '';
            Notification::send($circle->members()->get(), new ReadingCircleUpdate($circle, 'reminder', null, $day, $passages));
            $circle->forceFill(['last_reminded_on' => $local->toDateString()])->saveQuietly();
            $sent++;
        });

        return $sent;
    }

    public function startPlan(User $user, ReadingPlan $plan): ReadingPlanProgress
    {
        return ReadingPlanProgress::firstOrCreate(
            ['reading_plan_id' => $plan->id, 'user_id' => $user->id],
            ['church_id' => $plan->church_id, 'started_on' => today(), 'completed_days' => []],
        );
    }

    /**
     * @return Collection<int, User>
     */
    protected function others(ReadingCircle $circle, User $except)
    {
        return $circle->members()->where('users.id', '!=', $except->id)->get();
    }

    protected function uniqueCode(): string
    {
        do {
            // No 0/O/1/I so codes are easy to read aloud and type.
            $code = collect(str_split('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'))->random(6)->implode('');
        } while (ReadingCircle::where('code', $code)->exists());

        return $code;
    }
}
