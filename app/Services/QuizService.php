<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizPlayer;
use App\Models\QuizSession;
use App\Models\User;
use App\Notifications\Games\QuizUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Live quizzes (Kahoot-style): the host drives the game, players answer on
 * their phones, scores reward correct *and* fast answers.
 */
class QuizService
{
    public function __construct(protected NotificationService $notifications) {}

    public function host(Quiz $quiz, User $host, bool $notifyMembers = false): QuizSession
    {
        abort_if($quiz->questions()->count() === 0, 422, __('Add at least one question first.'));

        $session = QuizSession::create([
            'church_id' => $quiz->church_id, 'quiz_id' => $quiz->id, 'host_id' => $host->id,
            'pin' => $this->uniquePin(), 'status' => 'lobby',
        ]);

        if ($notifyMembers) {
            $this->notifications->notifyChurch($quiz->church, new QuizUpdate($session->load('quiz', 'church'), 'starting'), 'members', ['except_user_id' => $host->id]);
        }

        return $session;
    }

    public function join(User $user, QuizSession $session): QuizPlayer
    {
        if (! $session->isActive()) {
            throw ValidationException::withMessages(['pin' => __('This game has already finished.')]);
        }

        return QuizPlayer::firstOrCreate(
            ['quiz_session_id' => $session->id, 'user_id' => $user->id],
            ['nickname' => mb_substr($user->name, 0, 40)],
        );
    }

    public function findByPin(string $pin): ?QuizSession
    {
        return QuizSession::active()->where('pin', preg_replace('/\D/', '', $pin))->latest()->first();
    }

    /**
     * Host controls. Each returns the fresh host state.
     */
    public function start(QuizSession $session): void
    {
        abort_unless($session->status === 'lobby', 422, __('The game has already started.'));
        $session->update(['status' => 'question', 'current_index' => 0, 'question_started_at' => now(), 'started_at' => now()]);
    }

    public function reveal(QuizSession $session): void
    {
        if ($session->status === 'question') {
            $session->update(['status' => 'reveal']);
        }
    }

    public function next(QuizSession $session): void
    {
        abort_unless(in_array($session->status, ['question', 'reveal'], true), 422);
        $count = $session->quiz->questions()->count();

        if ($session->current_index + 1 >= $count) {
            $this->finish($session);

            return;
        }

        $session->update(['status' => 'question', 'current_index' => $session->current_index + 1, 'question_started_at' => now()]);
    }

    public function finish(QuizSession $session): void
    {
        if (! $session->isActive()) {
            return;
        }
        $session->update(['status' => 'finished', 'finished_at' => now()]);

        // Congratulate the podium.
        $session->leaderboard(3)->load('user')->each(function (QuizPlayer $player, int $i) use ($session) {
            if ($player->score > 0) {
                $player->user?->notify(new QuizUpdate($session->loadMissing('quiz', 'church'), 'winner', $i + 1, $player->score));
            }
        });
    }

    public function cancel(QuizSession $session): void
    {
        if ($session->isActive()) {
            $session->update(['status' => 'cancelled', 'finished_at' => now()]);
        }
    }

    /**
     * Record an answer. Points = base × (1 − elapsed/limit ÷ 2) for a correct
     * answer, so a correct answer is always worth at least half.
     *
     * @return array{correct: null, points: null, locked: bool}
     */
    public function answer(QuizSession $session, QuizPlayer $player, int $option, int $questionIndex): array
    {
        $question = $session->quiz->questions()->skip($session->current_index)->first();
        $elapsed = (int) round($session->question_started_at->diffInMilliseconds(now(), true));

        if ($session->status !== 'question' || $questionIndex !== $session->current_index || ! $question
            || $elapsed > $question->time_limit * 1000 + 1500 || $option < 0 || $option >= count($question->options)) {
            throw ValidationException::withMessages(['option' => __('Time is up for this question.')]);
        }

        DB::transaction(function () use ($session, $player, $question, $option, $elapsed) {
            $correct = $option === $question->correct_option;
            $points = $correct ? (int) round($question->points * (1 - min(1, $elapsed / ($question->time_limit * 1000)) / 2)) : 0;

            $answer = QuizAnswer::firstOrCreate(
                ['quiz_player_id' => $player->id, 'quiz_question_id' => $question->id],
                ['quiz_session_id' => $session->id, 'option' => $option, 'is_correct' => $correct, 'points' => $points, 'response_ms' => min($elapsed, $question->time_limit * 1000)],
            );

            if ($answer->wasRecentlyCreated) {
                QuizPlayer::whereKey($player->id)->update([
                    'score' => DB::raw('score + '.$points),
                    'correct_count' => DB::raw('correct_count + '.($correct ? 1 : 0)),
                    'streak' => $correct ? DB::raw('streak + 1') : 0,
                ]);
            }
        });

        // Players learn if they were right only when the host reveals the answer.
        return ['correct' => null, 'points' => null, 'locked' => true];
    }

    /**
     * Everything a screen needs, in one poll.
     *
     * @return array<string, mixed>
     */
    public function state(QuizSession $session, ?User $viewer = null, bool $host = false): array
    {
        $session->refresh()->loadMissing('quiz');
        $questions = $session->quiz->questions()->get();
        $question = $session->current_index !== null ? $questions->get($session->current_index) : null;
        $revealed = in_array($session->status, ['reveal', 'finished'], true);
        $player = $viewer ? QuizPlayer::where('quiz_session_id', $session->id)->where('user_id', $viewer->id)->first() : null;

        $state = [
            'status' => $session->status,
            'pin' => $session->pin,
            'quiz' => $session->quiz->title,
            'total' => $questions->count(),
            'index' => $session->current_index,
            'players_count' => $session->players()->count(),
            'players' => $session->status === 'lobby' ? $session->players()->latest('id')->take(60)->pluck('nickname') : [],
            'leaderboard' => $this->board($session, $player, $session->status === 'finished' ? 50 : 10),
        ];

        if ($question && $session->status !== 'lobby') {
            $elapsed = (int) round($session->question_started_at->diffInMilliseconds(now(), true));
            $answers = QuizAnswer::where('quiz_session_id', $session->id)->where('quiz_question_id', $question->id);

            $state['question'] = [
                'text' => $question->question,
                'options' => $question->options,
                'time_limit' => $question->time_limit,
                'remaining_ms' => $session->status === 'question' ? max(0, $question->time_limit * 1000 - $elapsed) : 0,
                'answered_count' => (clone $answers)->count(),
            ];

            if ($revealed || $host) {
                $counts = (clone $answers)->selectRaw('`option`, COUNT(*) c')->groupBy('option')->pluck('c', 'option');
                $state['reveal'] = [
                    'correct' => $revealed || $host ? $question->correct_option : null,
                    'counts' => collect($question->options)->keys()->map(fn ($i) => (int) ($counts[$i] ?? 0))->all(),
                ];
            }

            if ($player) {
                $mine = (clone $answers)->where('quiz_player_id', $player->id)->first();
                $state['me'] = [
                    'answer' => $mine?->option,
                    'correct' => $revealed ? (bool) $mine?->is_correct : null,
                    'points' => $revealed ? (int) ($mine?->points ?? 0) : null,
                ];
            }
        }

        if ($player) {
            $player->refresh();
            $state['me'] = ($state['me'] ?? ['answer' => null]) + [
                'name' => $player->nickname,
                'score' => $player->score,
                'rank' => QuizPlayer::where('quiz_session_id', $session->id)->where(fn ($q) => $q->where('score', '>', $player->score)
                    ->orWhere(fn ($t) => $t->where('score', $player->score)->where('correct_count', '>', $player->correct_count)))->count() + 1,
                'streak' => $player->streak,
            ];
        }

        return $state;
    }

    /**
     * @return array<int, array{rank: int, name: string, score: int, correct: int, me: bool}>
     */
    protected function board(QuizSession $session, ?QuizPlayer $me, int $limit): array
    {
        return $session->leaderboard($limit)->values()->map(fn (QuizPlayer $p, int $i) => [
            'rank' => $i + 1, 'name' => $p->nickname, 'score' => $p->score, 'correct' => $p->correct_count, 'me' => $me?->id === $p->id,
        ])->all();
    }

    protected function uniquePin(): string
    {
        do {
            $pin = (string) random_int(100000, 999999);
        } while (QuizSession::active()->where('pin', $pin)->exists());

        return $pin;
    }
}
