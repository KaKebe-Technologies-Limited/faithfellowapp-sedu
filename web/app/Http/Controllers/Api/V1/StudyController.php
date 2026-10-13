<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReadingCircle;
use App\Models\ReadingCircleEntry;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanProgress;
use App\Models\User;
use App\Services\ReadingCircleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reading plans (a passage a day, with streaks) and reading circles (up to four partners reading the same
 * plan together, sharing a private summary after each day).
 */
class StudyController extends Controller
{
    public function __construct(protected ReadingCircleService $circles) {}

    public function plans(Request $request): JsonResponse
    {
        $user = $request->user();

        $mine = ReadingPlanProgress::with(['plan', 'church.media'])->where('user_id', $user->id)->latest('updated_at')->get()->filter(fn ($p) => $p->plan);
        $available = ReadingPlan::with('church.media')->forChurches($user->memberChurchIds())->where('is_published', true)
            ->whereNotIn('id', $mine->pluck('reading_plan_id'))->latest()->get();
        $circles = ReadingCircle::with(['plan', 'members.media', 'memberships'])->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id))->get();

        return response()->json(['data' => [
            'mine' => $mine->map(fn ($p) => $this->plan($p->plan, $p))->values(),
            'available' => $available->map(fn ($p) => $this->plan($p))->values(),
            'circles' => $circles->map(fn ($c) => $this->circle($c, $user))->values(),
        ]]);
    }

    /** @return array<string, mixed> */
    protected function plan(ReadingPlan $plan, ?ReadingPlanProgress $progress = null): array
    {
        $plan->loadMissing('church.media');
        $progress?->setRelation('plan', $plan);

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'description' => $plan->description,
            'days_count' => $plan->days_count,
            'church' => ['id' => $plan->church->id, 'slug' => $plan->church->slug, 'name' => $plan->church->name, 'logo_url' => $plan->church->logo_url],
            'progress' => $progress ? [
                'percent' => $progress->percent(),
                'completed' => count($progress->completed_days ?? []),
                'next_day' => $progress->nextDay(),
                'todays_day' => $progress->todaysDay(),
                'streak' => (int) $progress->current_streak,
                'longest_streak' => (int) $progress->longest_streak,
                'started_on' => $progress->started_on?->toDateString(),
                'completed_at' => $progress->completed_at?->toIso8601String(),
            ] : null,
        ];
    }

    public function showPlan(Request $request, ReadingPlan $readingPlan): JsonResponse
    {
        $this->authorize('view', $readingPlan);
        $user = $request->user();
        $progress = ReadingPlanProgress::where('reading_plan_id', $readingPlan->id)->where('user_id', $user->id)->first();
        $progress?->setRelation('plan', $readingPlan);
        $day = max(1, min($readingPlan->days_count, (int) $request->query('day', $progress?->nextDay() ?? 1)));
        $done = $progress?->completed_days ?? [];

        return response()->json(['data' => $this->plan($readingPlan, $progress) + [
            'day' => ['day' => $day, ...($readingPlan->entryForDay($day) ?? ['title' => null, 'passages' => '', 'reflection' => null]), 'done' => in_array($day, $done, true)],
            'days' => collect($readingPlan->entries)->map(fn ($e) => ['day' => $e['day'], 'passages' => $e['passages'] ?? '', 'done' => in_array($e['day'], $done, true)])->values(),
            'circles' => ReadingCircle::with(['plan', 'members.media', 'memberships'])->where('reading_plan_id', $readingPlan->id)
                ->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id))->get()->map(fn ($c) => $this->circle($c, $user))->values(),
            'can_start_circle' => $user->isMemberOf($readingPlan->church_id),
        ]]);
    }

    public function start(Request $request, ReadingPlan $readingPlan): JsonResponse
    {
        $this->authorize('view', $readingPlan);
        $progress = ReadingPlanProgress::firstOrCreate(
            ['reading_plan_id' => $readingPlan->id, 'user_id' => $request->user()->id],
            ['church_id' => $readingPlan->church_id, 'started_on' => today(), 'completed_days' => [], 'reminder_time' => $request->input('reminder_time')],
        );

        return response()->json(['data' => $this->plan($readingPlan, $progress)], 201);
    }

    public function complete(Request $request, ReadingPlan $readingPlan): JsonResponse
    {
        $data = $request->validate(['day' => ['required', 'integer', 'min:1', 'max:'.$readingPlan->days_count]]);
        $progress = ReadingPlanProgress::where('reading_plan_id', $readingPlan->id)->where('user_id', $request->user()->id)->firstOrFail();
        $progress->setRelation('plan', $readingPlan);
        $progress->markRead((int) $data['day']);

        return response()->json(['data' => $this->plan($readingPlan, $progress->refresh())]);
    }

    public function leave(Request $request, ReadingPlan $readingPlan): JsonResponse
    {
        ReadingPlanProgress::where('reading_plan_id', $readingPlan->id)->where('user_id', $request->user()->id)->delete();

        return response()->json(['left' => true]);
    }

    // ── Reading circles ─────────────────────────────────────────────────────────────────────────────────

    public function createCircle(Request $request, ReadingPlan $readingPlan): JsonResponse
    {
        $this->authorize('view', $readingPlan);
        abort_unless($request->user()->isMemberOf($readingPlan->church_id), 403);

        $circle = $this->circles->create($request->user(), $readingPlan, $this->schedule($request) + $request->validate(['name' => ['required', 'string', 'max:80']]));

        return response()->json(['data' => $this->circle($circle->load(['plan', 'members.media', 'memberships']), $request->user())], 201);
    }

    public function joinCircle(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $circle = $this->circles->join($request->user(), $data['code']);

        return response()->json(['data' => $this->circle($circle->load(['plan', 'members.media', 'memberships']), $request->user())]);
    }

    public function showCircle(Request $request, ReadingCircle $readingCircle): JsonResponse
    {
        $user = $request->user();
        $circle = $readingCircle->load(['plan', 'members.media', 'memberships']);
        abort_unless($circle->hasMember($user), 404);

        $current = $circle->currentDay();
        $day = max(1, min($circle->plan->days_count, (int) $request->query('day', $current)));
        $entries = ReadingCircleEntry::where('reading_circle_id', $circle->id)->where('day', $day)->get()->keyBy('user_id');

        return response()->json(['data' => $this->circle($circle, $user) + [
            'current_day' => $current,
            'day' => ['day' => $day, ...($circle->plan->entryForDay($day) ?? ['title' => null, 'passages' => '', 'reflection' => null])],
            'entries' => $circle->members->map(function (User $m) use ($entries, $user) {
                $entry = $entries->get($m->id);

                return [
                    'user_id' => $m->id,
                    'name' => $m->id === $user->id ? __('You') : $m->name,
                    'avatar_url' => $m->avatar_url,
                    'done' => (bool) $entry?->completed_at,
                    'completed_at' => $entry?->completed_at?->toIso8601String(),
                    'summary' => $entry?->summary,
                ];
            })->values(),
        ]]);
    }

    public function completeCircleDay(Request $request, ReadingCircle $readingCircle, int $day): JsonResponse
    {
        abort_unless($readingCircle->hasMember($request->user()), 404);
        abort_unless($day >= 1 && $day <= $readingCircle->plan->days_count, 404);
        $data = $request->validate(['summary' => ['nullable', 'string', 'max:3000']]);

        $this->circles->complete($request->user(), $readingCircle, $day, $data['summary'] ?? null);

        return response()->json(['done' => true, 'day' => $day]);
    }

    public function updateCircle(Request $request, ReadingCircle $readingCircle): JsonResponse
    {
        abort_unless($readingCircle->memberships()->where('user_id', $request->user()->id)->value('role') === 'owner', 403);
        $readingCircle->update($this->schedule($request) + $request->validate(['name' => ['required', 'string', 'max:80']]));

        return response()->json(['data' => $this->circle($readingCircle->load(['plan', 'members.media', 'memberships']), $request->user())]);
    }

    public function leaveCircle(Request $request, ReadingCircle $readingCircle): JsonResponse
    {
        abort_unless($readingCircle->hasMember($request->user()), 404);
        $this->circles->leave($request->user(), $readingCircle);

        return response()->json(['left' => true]);
    }

    /** @return array<string, mixed> */
    protected function circle(ReadingCircle $c, User $viewer): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'code' => $c->code,
            'schedule' => $c->scheduleLabel(),
            'reading_time' => substr((string) $c->reading_time, 0, 5),
            'reading_days' => $c->reading_days ?? [],
            'max_members' => (int) $c->max_members,
            'is_owner' => $c->memberships->firstWhere('user_id', $viewer->id)?->role === 'owner',
            'plan' => ['id' => $c->plan->id, 'title' => $c->plan->title, 'days_count' => $c->plan->days_count],
            'members' => $c->members->map(fn (User $m) => ['id' => $m->id, 'name' => $m->name, 'avatar_url' => $m->avatar_url])->values(),
        ];
    }

    /** @return array{reading_time: string, reading_days: array<int, int>} */
    protected function schedule(Request $request): array
    {
        $data = $request->validate([
            'reading_time' => ['required', 'date_format:H:i'],
            'reading_days' => ['required', 'array', 'min:1'],
            'reading_days.*' => ['integer', Rule::in(range(0, 6))],
        ]);

        return ['reading_time' => $data['reading_time'], 'reading_days' => array_values(array_unique(array_map('intval', $data['reading_days'])))];
    }
}
