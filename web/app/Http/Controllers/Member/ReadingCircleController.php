<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ReadingCircle;
use App\Models\ReadingCircleEntry;
use App\Models\ReadingPlan;
use App\Services\ReadingCircleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reading partners: create a circle for a plan, share the code, read together.
 */
class ReadingCircleController extends Controller
{
    public function __construct(protected ReadingCircleService $circles) {}

    public function store(Request $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('view', $readingPlan);
        abort_unless($request->user()->isMemberOf($readingPlan->church_id), 403);

        $circle = $this->circles->create($request->user(), $readingPlan, $this->schedule($request) + [
            'name' => $request->validate(['name' => ['required', 'string', 'max:80']])['name'],
        ]);

        return redirect()->route('reading-circles.show', $circle)
            ->with('success', __('Circle created! Share the code :code with up to :n partners.', ['code' => $circle->code, 'n' => $circle->max_members - 1]));
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $circle = $this->circles->join($request->user(), $data['code']);

        return redirect()->route('reading-circles.show', $circle)->with('success', __('Welcome to :circle! You will be reminded together.', ['circle' => $circle->name]));
    }

    public function show(Request $request, ReadingCircle $readingCircle): View
    {
        $circle = $readingCircle->load(['plan.church.media', 'members.media']);
        abort_unless($circle->hasMember($request->user()), 404);

        $currentDay = $circle->currentDay();
        $day = max(1, min($circle->plan->days_count, (int) $request->query('day', $currentDay)));
        $entries = ReadingCircleEntry::where('reading_circle_id', $circle->id)->where('day', $day)->get()->keyBy('user_id');

        return view('member.reading-circles.show', [
            'circle' => $circle,
            'day' => $day,
            'currentDay' => $currentDay,
            'entry' => $circle->plan->entryForDay($day),
            'entries' => $entries,
            'mine' => $entries[$request->user()->id] ?? null,
            'isOwner' => $circle->memberships()->where('user_id', $request->user()->id)->value('role') === 'owner',
            'history' => ReadingCircleEntry::where('reading_circle_id', $circle->id)->whereNotNull('completed_at')
                ->selectRaw('day, COUNT(*) done')->groupBy('day')->orderByDesc('day')->take(14)->pluck('done', 'day'),
        ]);
    }

    public function complete(Request $request, ReadingCircle $readingCircle, int $day): RedirectResponse
    {
        abort_unless($readingCircle->hasMember($request->user()), 404);
        abort_unless($day >= 1 && $day <= $readingCircle->plan->days_count, 404);
        $data = $request->validate(['summary' => ['nullable', 'string', 'max:3000']]);

        $this->circles->complete($request->user(), $readingCircle, $day, $data['summary'] ?? null);

        return redirect()->route('reading-circles.show', [$readingCircle, 'day' => $day])
            ->with('success', __('Day :day done! Your partners have been told. 🙌', ['day' => $day]));
    }

    public function update(Request $request, ReadingCircle $readingCircle): RedirectResponse
    {
        abort_unless($readingCircle->memberships()->where('user_id', $request->user()->id)->value('role') === 'owner', 403);
        $readingCircle->update($this->schedule($request) + $request->validate(['name' => ['required', 'string', 'max:80']]));

        return back()->with('success', __('Reading time updated for everyone.'));
    }

    public function leave(Request $request, ReadingCircle $readingCircle): RedirectResponse
    {
        abort_unless($readingCircle->hasMember($request->user()), 404);
        $plan = $readingCircle->plan;
        $this->circles->leave($request->user(), $readingCircle);

        return redirect()->route('reading-plans.show', $plan)->with('success', __('You left the circle. Your own plan progress is kept.'));
    }

    /**
     * @return array{reading_time: string, reading_days: array<int, int>}
     */
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
