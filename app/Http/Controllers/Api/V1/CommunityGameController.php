<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\Quiz;
use App\Models\QuizPlayer;
use App\Models\QuizSession;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\QuizService;
use App\Support\SampleGames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Community games: any member can pick one of the ready-made quizzes, start a game, share its PIN or invite the people they
 * have played with, and press Start when everyone is in. Playing is the same as in a church game (GameController).
 */
class CommunityGameController extends Controller
{
    public function __construct(protected QuizService $quizzes) {}

    /** The ready-made games, grouped by category. */
    public function library(Request $request): JsonResponse
    {
        $plays = QuizSession::whereNull('church_id')->selectRaw('quiz_id, COUNT(*) as c')->groupBy('quiz_id')->pluck('c', 'quiz_id');
        // The member's best finished run of each quiz (once the database update for self-paced games is applied).
        $best = QuizService::selfPaced() ? rescue(fn () => QuizPlayer::query()->join('quiz_sessions', 'quiz_sessions.id', '=', 'quiz_players.quiz_session_id')
            ->where('quiz_players.user_id', $request->user()->id)->where('quiz_sessions.mode', 'self')->whereNotNull('quiz_players.finished_at')
            ->selectRaw('quiz_sessions.quiz_id as quiz_id, MAX(quiz_players.score) as best, COUNT(*) as runs')->groupBy('quiz_sessions.quiz_id')->get()->keyBy('quiz_id'), collect(), false) : collect();

        return response()->json(['data' => [
            'categories' => collect(SampleGames::CATEGORIES)->map(fn ($c, $key) => ['key' => $key, 'label' => $c[0], 'color' => $c[1]])->values(),
            'quizzes' => Quiz::samples()->withCount('questions')->orderBy('id')->get()->map(fn (Quiz $q) => [
                'id' => $q->id, 'title' => $q->title, 'description' => $q->description, 'category' => $q->category, 'emoji' => $q->emoji,
                'questions' => (int) $q->questions_count, 'plays' => (int) ($plays[$q->id] ?? 0),
                'my_best' => isset($best[$q->id]) ? (int) $best[$q->id]->best : null, 'my_runs' => (int) ($best[$q->id]->runs ?? 0),
            ])->values(),
        ]]);
    }

    /** Start a game of a ready-made quiz: the member becomes the host and a player, and gets a PIN to share. */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate(['quiz_id' => ['required', 'integer']]);
        $quiz = Quiz::samples()->findOrFail($data['quiz_id']);
        $session = $this->quizzes->hostCommunity($quiz, $request->user());

        return response()->json(['data' => ['id' => $session->id, 'pin' => $session->pin, 'quiz' => $quiz->title]], 201);
    }

    /**
     * People this member can invite by name: everyone they have played a game with, and their connections. With `session`
     * each also says whether they were already invited or are already in.
     */
    public function friends(Request $request): JsonResponse
    {
        $me = $request->user();
        $session = $request->query('session') ? QuizSession::whereKey($request->query('session'))->where('host_id', $me->id)->first() : null;
        $ids = $this->friendIds($me);
        $played = QuizPlayer::whereIn('quiz_session_id', QuizPlayer::where('user_id', $me->id)->select('quiz_session_id'))
            ->whereIn('user_id', $ids ?: [0])->selectRaw('user_id, COUNT(*) as plays, MAX(created_at) as last_at')->groupBy('user_id')->get()->keyBy('user_id');
        $invited = $session ? $session->invites()->pluck('user_id')->all() : [];
        $inside = $session ? $session->players()->pluck('user_id')->all() : [];

        $people = User::with('media')->whereIn('id', $ids ?: [0])->get()
            ->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'avatar_url' => $u->avatar_url,
                'plays' => (int) ($played[$u->id]->plays ?? 0), 'last_played_at' => $played[$u->id]->last_at ?? null,
                'invited' => in_array($u->id, $invited, true), 'joined' => in_array($u->id, $inside, true),
            ])
            ->sortBy([['plays', 'desc'], ['name', 'asc']])->values();

        return response()->json(['data' => $people]);
    }

    /** Invite people you have played with: each is told in the app and on their phone. */
    public function invite(Request $request, QuizSession $quizSession): JsonResponse
    {
        $this->authorizeHost($request, $quizSession);
        $data = $request->validate(['user_ids' => ['required', 'array', 'min:1', 'max:30'], 'user_ids.*' => ['integer']]);

        $allowed = array_values(array_intersect($data['user_ids'], $this->friendIds($request->user())));
        $users = User::whereIn('id', $allowed ?: [0])->get();
        $sent = $this->quizzes->invite($quizSession, $request->user(), $users);

        return response()->json(['invited' => $sent, 'skipped' => count($data['user_ids']) - $users->count()]);
    }

    /**
     * The host starts the game: everyone who joined or was invited is told it has begun. In a self-paced game there is nobody to wait for:
     * each player starts their own run, at the first question, whenever they like (also days after the host).
     */
    public function begin(Request $request, QuizSession $quizSession): JsonResponse
    {
        if ($quizSession->isSelf()) {
            $me = $request->user();
            abort_unless($quizSession->church_id === null && ($quizSession->host_id === $me->id || $quizSession->players()->where('user_id', $me->id)->exists()
                || $quizSession->invites()->where('user_id', $me->id)->exists()), 404);
            $this->quizzes->beginSelf($quizSession->load('quiz'), $this->quizzes->join($me, $quizSession));

            return response()->json(['data' => $this->quizzes->state($quizSession, $me) + ['id' => $quizSession->id]]);
        }

        $this->authorizeHost($request, $quizSession);
        $this->quizzes->beginCommunity($quizSession, $request->user());

        return response()->json(['data' => $this->quizzes->state($quizSession, $request->user()) + ['id' => $quizSession->id]]);
    }

    /** The host closes the game (while waiting, or to end it early). */
    public function cancel(Request $request, QuizSession $quizSession): JsonResponse
    {
        $this->authorizeHost($request, $quizSession);
        $this->quizzes->cancel($quizSession);

        return response()->json(['cancelled' => true]);
    }

    protected function authorizeHost(Request $request, QuizSession $session): void
    {
        abort_unless($session->church_id === null && $session->host_id === $request->user()->id, 404);
    }

    /** @return array<int, int> */
    protected function friendIds(User $me): array
    {
        $blocked = UserBlock::idsAround($me->id);
        $sessions = QuizPlayer::where('user_id', $me->id)->select('quiz_session_id');

        return QuizPlayer::whereIn('quiz_session_id', $sessions)->where('user_id', '!=', $me->id)->pluck('user_id')
            ->merge(Connection::involving($me->id)->where('status', 'accepted')->get()->map(fn (Connection $c) => $c->otherThan($me)))
            ->unique()->reject(fn ($id) => in_array($id, $blocked, true))->map(fn ($id) => (int) $id)->values()->all();
    }
}
