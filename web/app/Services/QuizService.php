<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizPlayer;
use App\Models\QuizQuestion;
use App\Models\QuizSession;
use App\Models\User;
use App\Notifications\Games\GameInvite;
use App\Notifications\Games\QuizUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Live quizzes (Kahoot-style): the host starts the game, then it runs by
 * itself — every question is open for the same few seconds, the answer and
 * leaderboard show briefly, and the next question follows. Scores reward
 * correct *and* fast answers.
 */
class QuizService
{
    /** Answers sent at the last moment still count while they travel over slow networks. */
    public const GRACE_MS = 1000;

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

    /**
     * A community game: any member picks one of the ready-made quizzes and starts a game of it. The member is the host
     * and also a player; others join with the PIN or from an invitation. The host presses "Start" when everyone is in.
     */
    public function hostCommunity(Quiz $quiz, User $host): QuizSession
    {
        abort_unless($quiz->is_sample && $quiz->church_id === null, 404);
        abort_if($quiz->questions()->count() === 0, 422, __('This quiz has no questions yet.'));

        // Games nobody finished do not pile up, and a member has one waiting game at a time.
        QuizSession::active()->whereNull('church_id')->where(fn ($q) => $q->where('created_at', '<', now()->subHours(6))
            ->orWhere(fn ($w) => $w->where('host_id', $host->id)->where('status', 'lobby')))
            ->update(['status' => 'cancelled', 'finished_at' => now()]);

        $session = QuizSession::create(['church_id' => null, 'quiz_id' => $quiz->id, 'host_id' => $host->id, 'pin' => $this->uniquePin(), 'status' => 'lobby']);
        $this->join($host, $session);

        return $session->load('quiz');
    }

    /** The host starts a community game; everyone who joined or was invited is told. */
    public function beginCommunity(QuizSession $session, User $host): void
    {
        abort_unless($session->church_id === null && $session->host_id === $host->id, 403);
        abort_unless($session->status === 'lobby', 422, __('The game has already started.'));

        $this->start($session);

        $session->loadMissing('quiz');
        $ids = $session->players()->pluck('user_id')->merge($session->invites()->pluck('user_id'))->unique()->reject(fn ($id) => $id === $host->id);
        User::whereIn('id', $ids)->get()->each(fn (User $user) => $this->tell($user, $session, 'started', $host));
    }

    /** Invite members to a community game: each gets a notification in the app and a push to the phone. */
    public function invite(QuizSession $session, User $host, iterable $users): int
    {
        abort_unless($session->church_id === null && $session->host_id === $host->id && $session->isActive(), 403);

        $sent = 0;
        foreach ($users as $user) {
            $invite = $session->invites()->firstOrCreate(['user_id' => $user->id], ['invited_by' => $host->id]);
            if ($invite->wasRecentlyCreated) {
                $this->tell($user, $session->loadMissing('quiz'), 'invite', $host);
                $sent++;
            }
        }

        return $sent;
    }

    protected function tell(User $user, QuizSession $session, string $kind, User $from): void
    {
        $note = new GameInvite($session, $kind, $from);
        $user->notify($note);
        app(DevicePushService::class)->toUser($user, 'games', [
            'title' => $note->title(),
            'body' => $note->body(),
            'data' => ['kind' => 'game', 'ref' => $session->id],
            'channelId' => $kind === 'started' ? 'live' : 'default',
        ]);
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

    /** Seconds each question is open. */
    public static function questionSeconds(): int
    {
        return max(5, (int) config('faithfellows.quizzes.question_seconds', 10));
    }

    /** Seconds the answer and leaderboard show before the next question. */
    public static function revealSeconds(): int
    {
        return max(0, (int) config('faithfellows.quizzes.reveal_seconds', 5));
    }

    public function start(QuizSession $session): void
    {
        abort_unless($session->status === 'lobby', 422, __('The game has already started.'));
        $this->transition($session, ['status' => 'question', 'current_index' => 0, 'question_started_at' => $this->now($session), 'revealed_at' => null, 'started_at' => $this->now($session)]);
    }

    /**
     * The game clock. Every poll (host, players or spectators) calls this, so a
     * quiz runs by itself: question → answer + leaderboard → next question.
     * Transitions are conditional updates, so simultaneous polls move it once.
     */
    public function advance(QuizSession $session): void
    {
        if ($session->status === 'question' && $session->question_started_at) {
            $elapsed = $session->question_started_at->diffInMilliseconds(now(), true);
            $players = $session->players()->count();
            $answered = $players > 0 && $session->answers()->where('quiz_question_id', $this->currentQuestion($session)?->id)->count() >= $players;

            if ($answered || $elapsed >= self::questionSeconds() * 1000 + self::GRACE_MS) {
                $this->reveal($session);
            }
        } elseif ($session->status === 'reveal'
            && (! $session->revealed_at || $session->revealed_at->diffInMilliseconds(now(), true) >= self::revealSeconds() * 1000)) {
            $this->next($session);
        }
    }

    /** Show the answer now (also the host's "skip" during a question). */
    public function reveal(QuizSession $session): void
    {
        if ($session->status === 'question') {
            $this->transition($session, ['status' => 'reveal', 'revealed_at' => $this->now($session)]);
        }
    }

    /** Move to the next question, or finish after the last one. */
    public function next(QuizSession $session): void
    {
        abort_unless(in_array($session->status, ['question', 'reveal'], true), 422);

        if ($session->current_index + 1 >= $session->quiz->questions()->count()) {
            $this->finish($session);

            return;
        }

        $this->transition($session, ['status' => 'question', 'current_index' => $session->current_index + 1, 'question_started_at' => $this->now($session), 'revealed_at' => null]);
    }

    public function finish(QuizSession $session): void
    {
        if (! $this->transition($session, ['status' => 'finished', 'finished_at' => $this->now($session)])) {
            return;
        }

        // Congratulate the podium.
        $session->leaderboard(3)->load('user')->each(function (QuizPlayer $player, int $i) use ($session) {
            if ($player->score > 0) {
                $player->user?->notify(new QuizUpdate($session->loadMissing('quiz', 'church'), 'winner', $i + 1, $player->score));
            }
        });
    }

    public function cancel(QuizSession $session): void
    {
        $this->transition($session, ['status' => 'cancelled', 'finished_at' => $this->now($session)]);
    }

    /**
     * Record an answer. Points = base × (1 − elapsed/limit ÷ 2) for a correct
     * answer, so a correct answer is always worth at least half.
     *
     * @return array{correct: null, points: null, locked: bool}
     */
    public function answer(QuizSession $session, QuizPlayer $player, int $option, int $questionIndex): array
    {
        $question = $this->currentQuestion($session);
        $limit = self::questionSeconds() * 1000;
        $elapsed = (int) round($session->question_started_at->diffInMilliseconds(now(), true));

        if ($session->status !== 'question' || $questionIndex !== $session->current_index || ! $question
            || $elapsed > $limit + self::GRACE_MS || $option < 0 || $option >= count($question->options)) {
            throw ValidationException::withMessages(['option' => __('Time is up for this question.')]);
        }

        DB::transaction(function () use ($session, $player, $question, $option, $elapsed, $limit) {
            $correct = $option === $question->correct_option;
            $points = $correct ? (int) round($question->points * (1 - min(1, $elapsed / $limit) / 2)) : 0;

            $answer = QuizAnswer::firstOrCreate(
                ['quiz_player_id' => $player->id, 'quiz_question_id' => $question->id],
                ['quiz_session_id' => $session->id, 'option' => $option, 'is_correct' => $correct, 'points' => $points, 'response_ms' => min($elapsed, $limit)],
            );

            if ($answer->wasRecentlyCreated) {
                QuizPlayer::whereKey($player->id)->update([
                    'score' => DB::raw('score + '.$points),
                    'correct_count' => DB::raw('correct_count + '.($correct ? 1 : 0)),
                    'streak' => $correct ? DB::raw('streak + 1') : 0,
                ]);
            }
        });

        // Players learn if they were right when the answer is shown.
        return ['correct' => null, 'points' => null, 'locked' => true];
    }

    /**
     * Everything a screen needs, in one poll. Also ticks the game clock.
     *
     * @return array<string, mixed>
     */
    public function state(QuizSession $session, ?User $viewer = null, bool $host = false): array
    {
        $session->refresh()->loadMissing('quiz');
        $this->advance($session);

        $questions = $session->quiz->questions()->get();
        $question = $session->current_index !== null ? $questions->get($session->current_index) : null;
        $revealed = in_array($session->status, ['reveal', 'finished'], true);
        $player = $viewer ? QuizPlayer::where('quiz_session_id', $session->id)->where('user_id', $viewer->id)->first() : null;
        $answers = $question ? QuizAnswer::where('quiz_session_id', $session->id)->where('quiz_question_id', $question->id) : null;
        $live = in_array($session->status, ['question', 'reveal'], true);

        $state = [
            'status' => $session->status,
            'pin' => $session->pin,
            'quiz' => $session->quiz->title,
            'community' => $session->church_id === null,
            'is_host' => $viewer !== null && $session->host_id === $viewer->id,
            'host_name' => $session->host?->name,
            'total' => $questions->count(),
            'index' => $session->current_index,
            'timing' => ['question' => self::questionSeconds(), 'reveal' => self::revealSeconds()],
            'players_count' => $session->players()->count(),
            'players' => $session->status === 'lobby' ? $session->players()->latest('id')->take(60)->pluck('nickname') : [],
            'leaderboard' => $this->board($session, $player, $session->status === 'finished' ? 50 : 10,
                $live && $answers ? (clone $answers)->get(['quiz_player_id', 'points'])->keyBy('quiz_player_id') : collect()),
        ];

        if ($question && $session->status !== 'lobby') {
            $elapsed = (int) round($session->question_started_at->diffInMilliseconds(now(), true));

            $state['question'] = [
                'text' => $question->question,
                'options' => $question->options,
                'time_limit' => self::questionSeconds(),
                'remaining_ms' => $session->status === 'question' ? max(0, self::questionSeconds() * 1000 - $elapsed) : 0,
                'answered_count' => (clone $answers)->count(),
            ];

            if ($session->status === 'reveal' && $session->revealed_at) {
                $state['next_in_ms'] = max(0, self::revealSeconds() * 1000 - (int) round($session->revealed_at->diffInMilliseconds(now(), true)));
                $state['is_last'] = $session->current_index + 1 >= $questions->count();
            }

            if ($revealed || $host) {
                $counts = (clone $answers)->selectRaw('`option`, COUNT(*) c')->groupBy('option')->pluck('c', 'option');
                $state['reveal'] = [
                    'correct' => $question->correct_option,
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
                'id' => $player->id,
                'name' => $player->nickname,
                'score' => $player->score,
                'rank' => QuizPlayer::where('quiz_session_id', $session->id)->where(fn ($q) => $q->where('score', '>', $player->score)
                    ->orWhere(fn ($t) => $t->where('score', $player->score)->where('correct_count', '>', $player->correct_count)))->count() + 1,
                'streak' => $player->streak,
            ];
        }

        return $state;
    }

    protected function currentQuestion(QuizSession $session): ?QuizQuestion
    {
        return $session->current_index === null ? null : $session->quiz->questions()->skip($session->current_index)->first();
    }

    /**
     * Apply a state change only if nobody else changed the session first.
     */
    protected function transition(QuizSession $session, array $changes): bool
    {
        $moved = QuizSession::whereKey($session->id)
            ->where('status', $session->status)
            ->where(fn ($q) => $session->current_index === null ? $q->whereNull('current_index') : $q->where('current_index', $session->current_index))
            ->whereIn('status', QuizSession::ACTIVE)
            ->update($changes) === 1;

        $session->refresh();

        return $moved;
    }

    /** "Now" with milliseconds, for raw conditional updates. */
    protected function now(QuizSession $session): string
    {
        return $session->fromDateTime(now());
    }

    /**
     * @return array<int, array{id: int, rank: int, name: string, score: int, correct: int, streak: int, answered: bool, gained: int, me: bool}>
     */
    protected function board(QuizSession $session, ?QuizPlayer $me, int $limit, Collection $current): array
    {
        return $session->leaderboard($limit)->values()->map(fn (QuizPlayer $p, int $i) => [
            'id' => $p->id, 'rank' => $i + 1, 'name' => $p->nickname, 'score' => $p->score, 'correct' => $p->correct_count, 'streak' => $p->streak,
            'answered' => $current->has($p->id), 'gained' => (int) ($current[$p->id]->points ?? 0), 'me' => $me?->id === $p->id,
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
