<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Fun & games: build multiple-choice quizzes and host them live.
 */
class QuizController extends Controller
{
    public function __construct(protected QuizService $quizzes) {}

    public function index(Church $church): View
    {
        return view('church-admin.quizzes.index', [
            'church' => $church,
            'quizzes' => $church->quizzes()->withCount(['questions', 'sessions'])->latest()->get(),
            'live' => $church->quizSessions()->active()->with('quiz')->withCount('players')->latest()->get(),
            'recent' => $church->quizSessions()->where('status', 'finished')->with(['quiz', 'players' => fn ($q) => $q->orderByDesc('score')->limit(1)])
                ->withCount('players')->latest('finished_at')->take(8)->get(),
        ]);
    }

    public function create(Church $church): View
    {
        return $this->form($church, new Quiz(['theme' => 'magenta']));
    }

    public function store(Request $request, Church $church): RedirectResponse
    {
        $quiz = DB::transaction(function () use ($request, $church) {
            $quiz = $church->quizzes()->create([...$this->validated($request), 'user_id' => $request->user()->id]);
            $this->saveQuestions($quiz, $request);

            return $quiz;
        });

        return redirect()->route('church-admin.quizzes.index', $church)->with('success', __('Quiz “:t” saved with :n questions.', ['t' => $quiz->title, 'n' => $quiz->questions()->count()]));
    }

    public function edit(Church $church, Quiz $quiz): View
    {
        return $this->form($church, $quiz->load('questions'));
    }

    public function update(Request $request, Church $church, Quiz $quiz): RedirectResponse
    {
        DB::transaction(function () use ($request, $quiz) {
            $quiz->update($this->validated($request));
            $this->saveQuestions($quiz, $request);
        });

        return redirect()->route('church-admin.quizzes.index', $church)->with('success', __('Quiz updated.'));
    }

    public function destroy(Church $church, Quiz $quiz): RedirectResponse
    {
        $quiz->delete();

        return back()->with('success', __('Quiz deleted.'));
    }

    /**
     * Open a lobby with a fresh PIN (optionally inviting all members).
     */
    public function host(Request $request, Church $church, Quiz $quiz): RedirectResponse
    {
        $session = $this->quizzes->host($quiz, $request->user(), $request->boolean('notify'));

        return redirect()->route('church-admin.quizzes.session', [$church, $session]);
    }

    public function session(Church $church, QuizSession $quizSession): View
    {
        return view('church-admin.quizzes.host', [
            'church' => $church,
            'session' => $quizSession->load('quiz'),
            'state' => $this->quizzes->state($quizSession, null, true),
        ]);
    }

    public function state(Church $church, QuizSession $quizSession): JsonResponse
    {
        return response()->json($this->quizzes->state($quizSession, null, true));
    }

    public function action(Church $church, QuizSession $quizSession, string $action): JsonResponse
    {
        match ($action) {
            'start' => $this->quizzes->start($quizSession),
            'reveal' => $this->quizzes->reveal($quizSession),
            'next' => $this->quizzes->next($quizSession),
            'end' => $this->quizzes->finish($quizSession),
            'cancel' => $this->quizzes->cancel($quizSession),
        };

        return response()->json($this->quizzes->state($quizSession, null, true));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'questions' => ['required', 'array', 'min:1', 'max:50'],
            'questions.*.question' => ['required', 'string', 'max:300'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:4'],
            'questions.*.options.*' => ['nullable', 'string', 'max:120'],
            'questions.*.correct' => ['required', 'integer', 'between:0,3'],
            'questions.*.time_limit' => ['required', 'integer', Rule::in([10, 15, 20, 30, 45, 60])],
            'questions.*.points' => ['nullable', 'integer', Rule::in([500, 1000, 2000])],
        ], [], ['questions.*.question' => __('question'), 'questions.*.options' => __('answers')]);

        return $request->only(['title', 'description']);
    }

    protected function saveQuestions(Quiz $quiz, Request $request): void
    {
        $quiz->questions()->delete();

        foreach (array_values($request->input('questions', [])) as $i => $q) {
            // Drop empty answers but keep the correct one pointing at the right text.
            $options = collect($q['options'])->map(fn ($o) => trim((string) $o));
            $correctText = $options[(int) $q['correct']] ?? null;
            $options = $options->filter()->values();
            $correct = $options->search($correctText);

            abort_if($options->count() < 2 || $correct === false, 422, __('Question :n needs at least two answers and a correct answer.', ['n' => $i + 1]));

            $quiz->questions()->create([
                'position' => $i,
                'question' => trim($q['question']),
                'options' => $options->all(),
                'correct_option' => $correct,
                'time_limit' => (int) $q['time_limit'],
                'points' => (int) ($q['points'] ?? 1000),
            ]);
        }
    }

    protected function form(Church $church, Quiz $quiz): View
    {
        $questions = $quiz->exists
            ? $quiz->questions->map(fn ($q) => ['question' => $q->question, 'options' => array_pad($q->options, 4, ''), 'correct' => $q->correct_option, 'time_limit' => $q->time_limit, 'points' => $q->points])->all()
            : [['question' => '', 'options' => ['', '', '', ''], 'correct' => 0, 'time_limit' => 20, 'points' => 1000]];

        return view('church-admin.quizzes.form', ['church' => $church, 'quiz' => $quiz, 'questions' => old('questions', $questions)]);
    }
}
