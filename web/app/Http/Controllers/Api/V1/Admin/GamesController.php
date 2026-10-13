<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Hosting quiz games from the phone: write a quiz, start a game, show the PIN, and drive it (start, reveal,
 * next question, end) while members play on their own phones.
 */
class GamesController extends Controller
{
    public function __construct(protected QuizService $quizzes) {}

    public function index(Church $church): JsonResponse
    {
        return response()->json(['data' => [
            'quizzes' => $church->quizzes()->withCount('questions')->latest()->take(40)->get()->map(fn (Quiz $q) => ['id' => $q->id, 'title' => $q->title, 'description' => $q->description, 'questions' => (int) $q->questions_count])->values(),
            'active' => $church->quizSessions()->active()->with('quiz')->withCount('players')->latest()->get()->map(fn (QuizSession $s) => $this->session($s))->values(),
        ]]);
    }

    public function show(Church $church, Quiz $quiz): JsonResponse
    {
        return response()->json(['data' => [
            'id' => $quiz->id, 'title' => $quiz->title, 'description' => $quiz->description,
            'questions' => $quiz->questions->map(fn ($q) => ['question' => $q->question, 'options' => $q->options, 'correct' => (int) $q->correct_option, 'points' => (int) $q->points])->values(),
        ]]);
    }

    public function store(Request $request, Church $church): JsonResponse
    {
        $quiz = DB::transaction(function () use ($request, $church) {
            $quiz = $church->quizzes()->create([...$this->validated($request), 'user_id' => $request->user()->id]);
            $this->saveQuestions($quiz, $request);

            return $quiz;
        });

        return response()->json(['data' => ['id' => $quiz->id, 'questions' => $quiz->questions()->count()]], 201);
    }

    public function destroy(Church $church, Quiz $quiz): JsonResponse
    {
        $quiz->delete();

        return response()->json(['deleted' => true]);
    }

    public function host(Request $request, Church $church, Quiz $quiz): JsonResponse
    {
        $session = $this->quizzes->host($quiz, $request->user(), $request->boolean('notify'));

        return response()->json(['data' => $this->session($session->load('quiz'))], 201);
    }

    /** The host's view of a game: the same state the players get, plus how many chose each option. */
    public function state(Church $church, QuizSession $quizSession): JsonResponse
    {
        return response()->json(['data' => $this->quizzes->state($quizSession, null, true) + ['id' => $quizSession->id]]);
    }

    public function action(Church $church, QuizSession $quizSession, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['start', 'reveal', 'next', 'end', 'cancel'], true), 404);
        match ($action) {
            'start' => $this->quizzes->start($quizSession),
            'reveal' => $this->quizzes->reveal($quizSession),
            'next' => $this->quizzes->next($quizSession),
            'end' => $this->quizzes->finish($quizSession),
            'cancel' => $this->quizzes->cancel($quizSession),
        };

        return response()->json(['data' => $this->quizzes->state($quizSession, null, true) + ['id' => $quizSession->id]]);
    }

    /** @return array<string, mixed> */
    protected function session(QuizSession $s): array
    {
        return ['id' => $s->id, 'quiz' => $s->quiz->title, 'pin' => $s->pin, 'status' => $s->status, 'players_count' => (int) ($s->players_count ?? $s->players()->count())];
    }

    /** @return array<string, mixed> */
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
            'questions.*.points' => ['nullable', 'integer', Rule::in([500, 1000, 2000])],
        ]);

        return $request->only(['title', 'description']);
    }

    protected function saveQuestions(Quiz $quiz, Request $request): void
    {
        foreach (array_values($request->input('questions', [])) as $i => $q) {
            // Drop empty answers but keep the correct one pointing at the right text.
            $options = collect($q['options'])->map(fn ($o) => trim((string) $o));
            $correctText = $options[(int) $q['correct']] ?? null;
            $options = $options->filter()->values();
            $correct = $options->search($correctText);

            abort_if($options->count() < 2 || $correct === false, 422, __('Question :n needs at least two answers and a correct answer.', ['n' => $i + 1]));

            $quiz->questions()->create([
                'position' => $i, 'question' => trim($q['question']), 'options' => $options->all(), 'correct_option' => $correct,
                'time_limit' => QuizService::questionSeconds(), 'points' => (int) ($q['points'] ?? 1000),
            ]);
        }
    }
}
