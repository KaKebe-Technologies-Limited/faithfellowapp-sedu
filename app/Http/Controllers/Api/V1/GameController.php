<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChurchSummaryResource;
use App\Models\Quiz;
use App\Models\QuizInvite;
use App\Models\QuizPlayer;
use App\Models\QuizSession;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Live Bible quizzes in the app: see what is on, join with a PIN, play (the phone polls `state` about once a
 * second), answer, and watch the live scoreboard. Hosting a game is in the admin API.
 */
class GameController extends Controller
{
    public function __construct(protected QuizService $quizzes) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $churchIds = $user->memberChurchIds() ?: [0];
        $card = fn (QuizSession $s) => [
            'id' => $s->id,
            'quiz' => $s->quiz->title,
            'status' => $s->status,
            'players_count' => (int) $s->players_count,
            'church' => new ChurchSummaryResource($s->church),
            'finished_at' => $s->finished_at?->toIso8601String(),
        ];

        return response()->json(['data' => [
            'live' => QuizSession::active()->with(['quiz', 'church.media'])->withCount('players')->whereIn('church_id', $churchIds)->latest()->get()->map($card)->values(),
            'recent' => QuizSession::where('status', 'finished')->with(['quiz', 'church.media'])->withCount('players')->whereIn('church_id', $churchIds)->latest('finished_at')->take(9)->get()->map($card)->values(),
            // Community games: invitations waiting for you, and the games you started that nobody has finished yet.
            'invites' => rescue(fn () => QuizInvite::with(['session.quiz', 'inviter'])->where('user_id', $user->id)->whereHas('session', fn ($q) => $q->active())->latest()->take(10)->get()
                ->map(fn (QuizInvite $i) => ['session_id' => $i->quiz_session_id, 'quiz' => $i->session->quiz->title, 'emoji' => $i->session->quiz->emoji, 'from' => $i->inviter?->name, 'status' => $i->session->status, 'mode' => $i->session->isSelf() ? 'self' : 'live', 'at' => $i->created_at?->toIso8601String()])->values(), [], false),
            // A self-paced game stays open for friends for a week; it drops off this list once its host has played it to the end.
            'hosting' => rescue(fn () => QuizSession::active()->whereNull('church_id')->where('host_id', $user->id)
                ->when(QuizService::selfPaced(), fn ($q) => $q->whereDoesntHave('players', fn ($p) => $p->where('user_id', $user->id)->whereNotNull('finished_at')))
                ->with('quiz')->withCount('players')->latest()->get()
                ->map(fn (QuizSession $s) => ['id' => $s->id, 'quiz' => $s->quiz->title, 'emoji' => $s->quiz->emoji, 'pin' => $s->pin, 'status' => $s->status, 'mode' => $s->isSelf() ? 'self' : 'live', 'players_count' => (int) $s->players_count])->values(), [], false),
            'mine' => QuizPlayer::with('session.quiz')->where('user_id', $user->id)->latest()->take(5)->get()->map(fn (QuizPlayer $p) => [
                'session_id' => $p->quiz_session_id, 'quiz' => $p->session?->quiz?->title, 'quiz_id' => $p->session?->quiz_id, 'score' => (int) $p->score, 'correct' => (int) $p->correct_count,
                'finished' => $p->finished_at !== null, 'at' => $p->created_at?->toIso8601String(),
            ])->values(),
        ]]);
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate(['pin' => ['required', 'string', 'max:8']]);
        $session = $this->quizzes->findByPin($data['pin']);

        // A community game (no church) opens to anyone who has its PIN; a church game only to the church's members.
        if (! $session || ($session->church_id !== null && ! $request->user()->isMemberOf($session->church_id))) {
            throw ValidationException::withMessages(['pin' => __('No live game with that PIN.')]);
        }
        $this->quizzes->join($request->user(), $session);

        return response()->json(['data' => ['id' => $session->id]]);
    }

    /**
     * The records of a ready-made quiz: each person's best finished run, best first, for today, this week or all time. People are shown by the
     * name they like to be called. `scope=church` keeps to people who follow a church you follow.
     */
    public function records(Request $request): JsonResponse
    {
        $data = $request->validate([
            'quiz_id' => ['required', 'integer'],
            'period' => ['nullable', Rule::in(['today', 'week', 'all'])],
            'scope' => ['nullable', Rule::in(['everyone', 'church'])],
        ]);
        $quiz = Quiz::samples()->findOrFail($data['quiz_id']);
        $period = $data['period'] ?? 'week';

        if (! QuizService::selfPaced()) {
            return response()->json(['data' => ['quiz' => ['id' => $quiz->id, 'title' => $quiz->title, 'emoji' => $quiz->emoji, 'questions' => $quiz->questions()->count()],
                'period' => $period, 'scope' => 'everyone', 'since' => null, 'players' => 0, 'plays' => 0, 'rows' => [], 'me' => null]]);
        }

        return response()->json(['data' => $this->quizzes->records($quiz, $period, $request->user(), $data['scope'] ?? 'everyone')]);
    }

    /** "Next": leaves the moment the answer is shown and goes to the next question (self-paced games). */
    public function next(Request $request, QuizSession $quizSession): JsonResponse
    {
        $this->authorizeMember($request, $quizSession);
        abort_unless($quizSession->isSelf(), 422);
        $player = QuizPlayer::where('quiz_session_id', $quizSession->id)->where('user_id', $request->user()->id)->firstOrFail();
        $this->quizzes->continueSelf($quizSession->load('quiz'), $player);

        return response()->json(['data' => $this->quizzes->state($quizSession, $request->user()) + ['id' => $quizSession->id]]);
    }

    /** Everything one screen needs, once a second: the question, the clock, the leaderboard and the member's own standing. */
    public function state(Request $request, QuizSession $quizSession): JsonResponse
    {
        $this->authorizeMember($request, $quizSession);
        if ($quizSession->isActive()) {
            $this->quizzes->join($request->user(), $quizSession);
        }

        return response()->json(['data' => $this->quizzes->state($quizSession, $request->user()) + ['id' => $quizSession->id]]);
    }

    public function answer(Request $request, QuizSession $quizSession): JsonResponse
    {
        $this->authorizeMember($request, $quizSession);
        $data = $request->validate(['option' => ['required', 'integer', 'between:0,3'], 'question' => ['required', 'integer', 'min:0']]);
        $player = QuizPlayer::where('quiz_session_id', $quizSession->id)->where('user_id', $request->user()->id)->firstOrFail();

        $result = $this->quizzes->answer($quizSession->load('quiz'), $player, (int) $data['option'], (int) $data['question']);

        // In a self-paced game the person is told at once whether they were right, so the screen gets the state with the answer.
        return response()->json($quizSession->isSelf() ? $result + ['data' => $this->quizzes->state($quizSession, $request->user()) + ['id' => $quizSession->id]] : $result);
    }

    protected function authorizeMember(Request $request, QuizSession $session): void
    {
        if ($session->church_id === null) {
            $id = $request->user()->id;
            abort_unless($session->host_id === $id || $session->players()->where('user_id', $id)->exists() || $session->invites()->where('user_id', $id)->exists(), 404);

            return;
        }

        abort_unless($request->user()->isMemberOf($session->church_id) || $request->user()->canInChurch($session->church_id, 'quizzes.manage'), 404);
    }
}
