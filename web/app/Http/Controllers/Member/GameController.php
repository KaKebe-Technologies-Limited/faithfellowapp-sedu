<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\QuizPlayer;
use App\Models\QuizSession;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Members join live quizzes with a PIN, play on their phones and watch
 * live scoreboards.
 */
class GameController extends Controller
{
    public function __construct(protected QuizService $quizzes) {}

    public function index(Request $request): View
    {
        $churchIds = $request->user()->memberChurchIds() ?: [0];

        return view('member.games.index', [
            'live' => QuizSession::active()->with(['quiz', 'church.media'])->withCount('players')->whereIn('church_id', $churchIds)->latest()->get(),
            'recent' => QuizSession::where('status', 'finished')->with(['quiz', 'church.media'])->withCount('players')
                ->whereIn('church_id', $churchIds)->latest('finished_at')->take(9)->get(),
            'myGames' => QuizPlayer::with('session.quiz')->where('user_id', $request->user()->id)->latest()->take(5)->get(),
            'pin' => $request->query('pin'),
        ]);
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'string', 'max:8']]);
        $session = $this->quizzes->findByPin($data['pin']);

        if (! $session || ! $request->user()->isMemberOf($session->church_id)) {
            throw ValidationException::withMessages(['pin' => __('No live game with that PIN in your churches.')]);
        }

        $this->quizzes->join($request->user(), $session);

        return redirect()->route('games.play', $session);
    }

    public function play(Request $request, QuizSession $quizSession): View|RedirectResponse
    {
        $this->authorizeMember($request, $quizSession);

        if ($quizSession->isActive()) {
            $this->quizzes->join($request->user(), $quizSession);
        }

        return view('member.games.play', [
            'session' => $quizSession->load(['quiz', 'church.media']),
            'state' => $this->quizzes->state($quizSession, $request->user()),
        ]);
    }

    public function state(Request $request, QuizSession $quizSession): JsonResponse
    {
        $this->authorizeMember($request, $quizSession);

        return response()->json($this->quizzes->state($quizSession, $request->user()));
    }

    public function answer(Request $request, QuizSession $quizSession): JsonResponse
    {
        $this->authorizeMember($request, $quizSession);
        $data = $request->validate(['option' => ['required', 'integer', 'between:0,3'], 'question' => ['required', 'integer', 'min:0']]);
        $player = QuizPlayer::where('quiz_session_id', $quizSession->id)->where('user_id', $request->user()->id)->firstOrFail();

        return response()->json($this->quizzes->answer($quizSession->load('quiz'), $player, (int) $data['option'], (int) $data['question']));
    }

    /**
     * Live scoreboard for anyone in the church (big screen or spectators).
     */
    public function scores(Request $request, QuizSession $quizSession): View
    {
        $this->authorizeMember($request, $quizSession);

        return view('member.games.scores', [
            'session' => $quizSession->load(['quiz', 'church.media']),
            'state' => $this->quizzes->state($quizSession, $request->user()),
        ]);
    }

    protected function authorizeMember(Request $request, QuizSession $session): void
    {
        abort_unless($request->user()->isMemberOf($session->church_id) || $request->user()->canInChurch($session->church_id, 'quizzes.manage'), 404);
    }
}
