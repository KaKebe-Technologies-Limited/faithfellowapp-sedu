<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $mine = ReadingPlanProgress::with(['plan', 'church.media'])->where('user_id', $user->id)
            ->latest('updated_at')->get()->filter(fn ($p) => $p->plan);

        $available = ReadingPlan::with('church.media')
            ->forChurches($user->memberChurchIds())->where('is_published', true)
            ->whereNotIn('id', $mine->pluck('reading_plan_id'))
            ->latest()->get();

        return view('member.reading-plans.index', compact('mine', 'available'));
    }

    public function show(Request $request, ReadingPlan $readingPlan): View
    {
        $this->authorize('view', $readingPlan);
        $readingPlan->load('church.media');

        $progress = ReadingPlanProgress::where('reading_plan_id', $readingPlan->id)->where('user_id', $request->user()->id)->first();
        $progress?->setRelation('plan', $readingPlan);
        $day = (int) $request->query('day', $progress?->nextDay() ?? 1);
        $day = max(1, min($readingPlan->days_count, $day));

        return view('member.reading-plans.show', [
            'plan' => $readingPlan,
            'progress' => $progress,
            'day' => $day,
            'entry' => $readingPlan->entryForDay($day),
        ]);
    }

    public function start(Request $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('view', $readingPlan);

        ReadingPlanProgress::firstOrCreate(
            ['reading_plan_id' => $readingPlan->id, 'user_id' => $request->user()->id],
            ['church_id' => $readingPlan->church_id, 'started_on' => today(), 'completed_days' => [], 'reminder_time' => $request->input('reminder_time')],
        );

        return redirect()->route('reading-plans.show', $readingPlan)->with('success', __('Plan started — Day 1 is ready for you.'));
    }

    public function complete(Request $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $data = $request->validate(['day' => ['required', 'integer', 'min:1', 'max:'.$readingPlan->days_count]]);
        $progress = ReadingPlanProgress::where('reading_plan_id', $readingPlan->id)->where('user_id', $request->user()->id)->firstOrFail();
        $progress->setRelation('plan', $readingPlan);
        $progress->markRead((int) $data['day']);

        $message = $progress->completed_at
            ? __('🎉 You finished :plan!', ['plan' => $readingPlan->title])
            : __('Day :day done! 🔥 :streak-day streak.', ['day' => $data['day'], 'streak' => $progress->current_streak]);

        return redirect()->route('reading-plans.show', [$readingPlan, 'day' => $progress->nextDay()])->with('success', $message);
    }

    public function leave(Request $request, ReadingPlan $readingPlan): RedirectResponse
    {
        ReadingPlanProgress::where('reading_plan_id', $readingPlan->id)->where('user_id', $request->user()->id)->delete();

        return redirect()->route('reading-plans.index')->with('success', __('You left the plan.'));
    }
}
