<?php

namespace App\Services\Concerns;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizPlayer;
use App\Models\QuizSession;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Self-paced games, the way members play: everyone goes through the questions at their own pace, starting at the first question whenever they join.
 * A question closes when the person answers (the answer is shown for a moment, then the next question comes, or at once if they press Next) or when
 * its time runs out. Every finished run is a record: the best run of each person ranks on the quiz's board for today, this week and all time
 * (see records()). A game opened with a PIN is a room: friends who join with the PIN, even days later, start from the first question.
 *
 * The church-hosted games (the host moves everyone along together, on a screen in church) are the "live" mode of QuizService and are not touched here.
 */
trait SelfPacedQuizzes
{
    /** How long a person sees whether they were right before the next question appears. */
    public const FEEDBACK_MS = 2200;

    /** A game room stays open this many days for friends to join with its PIN. */
    public const SELF_OPEN_DAYS = 7;

    /** True once the database update that makes member games self-paced has been applied (until then the old, host-paced games run). */
    public static function selfPaced(): bool
    {
        static $ready = null;

        return $ready ??= (bool) rescue(fn () => Schema::hasColumn('quiz_sessions', 'mode') && Schema::hasColumn('quiz_players', 'finished_at'), false, false);
    }

    /** Opens a game room of a quiz. The host is the first player and presses Start playing when ready; friends join with the PIN any time. */
    public function hostSelf(Quiz $quiz, User $host): QuizSession
    {
        // A ready-made game is for everyone; a church's own quiz is for the members who follow that church.
        abort_unless(($quiz->is_sample && $quiz->church_id === null) || ($quiz->church_id !== null && $host->isMemberOf($quiz->church_id)), 404);
        abort_if($quiz->questions()->count() === 0, 422, __('This quiz has no questions yet.'));

        // Rooms nobody played in do not pile up: the host's earlier room of this quiz that no one else joined, and any room open longer than a week, are closed.
        QuizSession::active()->whereNull('church_id')->where('mode', 'self')->where(fn ($q) => $q->where('created_at', '<', now()->subDays(self::SELF_OPEN_DAYS))
            ->orWhere(fn ($w) => $w->where('host_id', $host->id)->where('quiz_id', $quiz->id)
                ->whereDoesntHave('players', fn ($p) => $p->where('user_id', '!=', $host->id))
                ->whereDoesntHave('players', fn ($p) => $p->whereNotNull('started_at'))))
            ->update(['status' => 'cancelled', 'finished_at' => now()]);

        $session = QuizSession::create(['church_id' => null, 'quiz_id' => $quiz->id, 'host_id' => $host->id, 'pin' => $this->uniquePin(), 'status' => 'question', 'mode' => 'self', 'started_at' => now()]);
        $this->join($host, $session);

        return $session->load('quiz');
    }

    /** The person starts their own run, at the first question. */
    public function beginSelf(QuizSession $session, QuizPlayer $player): void
    {
        abort_unless($session->isSelf() && $session->isActive(), 422, __('This game has closed.'));
        if ($player->started_at) {
            return;
        }
        $at = $this->stampFor($player);
        QuizPlayer::whereKey($player->id)->whereNull('started_at')->update(['started_at' => $at, 'current_index' => 0, 'question_started_at' => $at, 'revealed_at' => null, 'finished_at' => null]);
    }

    /** "Next": skips the rest of the moment the answer is shown. */
    public function continueSelf(QuizSession $session, QuizPlayer $player): void
    {
        $session->loadMissing('quiz');
        $total = $session->quiz->questions()->count();
        $player = $this->selfAdvance($session, $player, $total);
        if ($player->revealed_at !== null && ! $player->finished_at) {
            $this->selfMoveOn($player, $total);
        }
    }

    /**
     * The person's own clock, ticked by every poll: a question nobody answered closes when its time is up, and the answer moves on after a moment.
     * Conditional updates, so two polls at once move a person on only once.
     */
    protected function selfAdvance(QuizSession $session, QuizPlayer $player, int $total): QuizPlayer
    {
        $player->refresh();
        if (! $player->started_at || $player->finished_at || ! $session->isActive()) {
            return $player;
        }

        $limit = self::questionSeconds() * 1000;
        if ($player->revealed_at === null) {
            if ($player->question_started_at->diffInMilliseconds(now(), true) >= $limit + self::GRACE_MS) {
                // Time ran out with no answer: the answer is shown, nothing is scored, and a streak ends.
                QuizPlayer::whereKey($player->id)->where('current_index', $player->current_index)->whereNull('revealed_at')
                    ->update(['revealed_at' => $this->stampFor($player), 'streak' => 0, 'total_ms' => DB::raw('total_ms + '.$limit)]);
            }
        } elseif ($player->revealed_at->diffInMilliseconds(now(), true) >= self::FEEDBACK_MS) {
            $this->selfMoveOn($player, $total);
        }

        return $player->refresh();
    }

    protected function selfMoveOn(QuizPlayer $player, int $total): void
    {
        $at = $this->stampFor($player);
        $changes = $player->current_index + 1 >= $total
            ? ['finished_at' => $at]
            : ['current_index' => $player->current_index + 1, 'question_started_at' => $at, 'revealed_at' => null];

        QuizPlayer::whereKey($player->id)->where('current_index', $player->current_index)->whereNotNull('revealed_at')->whereNull('finished_at')->update($changes);
    }

    /** @return array{locked: bool, correct: bool, points: int} */
    protected function answerSelf(QuizSession $session, QuizPlayer $player, int $option, int $index): array
    {
        $questions = $session->quiz->questions()->get();
        $player = $this->selfAdvance($session, $player, $questions->count());
        $question = $questions->get($player->current_index);
        $limit = self::questionSeconds() * 1000;
        $closed = ValidationException::withMessages(['option' => __('Time is up for this question.')]);

        if (! $session->isActive() || ! $player->started_at || $player->finished_at || $player->revealed_at !== null || $index !== $player->current_index
            || ! $question || $option < 0 || $option >= count($question->options)) {
            throw $closed;
        }
        $elapsed = (int) round($player->question_started_at->diffInMilliseconds(now(), true));
        if ($elapsed > $limit + self::GRACE_MS) {
            throw $closed;
        }

        $correct = $option === $question->correct_option;
        $points = $correct ? (int) round($question->points * (1 - min(1, $elapsed / $limit) / 2)) : 0;

        DB::transaction(function () use ($session, $player, $question, $option, $elapsed, $limit, $correct, $points) {
            $claimed = QuizPlayer::whereKey($player->id)->where('current_index', $player->current_index)->whereNull('revealed_at')->update(['revealed_at' => $this->stampFor($player)]);
            if ($claimed !== 1) {
                throw ValidationException::withMessages(['option' => __('Time is up for this question.')]);
            }
            QuizAnswer::create(['quiz_session_id' => $session->id, 'quiz_player_id' => $player->id, 'quiz_question_id' => $question->id, 'option' => $option,
                'is_correct' => $correct, 'points' => $points, 'response_ms' => min($elapsed, $limit)]);
            QuizPlayer::whereKey($player->id)->update([
                'score' => DB::raw('score + '.$points),
                'correct_count' => DB::raw('correct_count + '.($correct ? 1 : 0)),
                'streak' => $correct ? DB::raw('streak + 1') : 0,
                'total_ms' => DB::raw('total_ms + '.min($elapsed, $limit)),
            ]);
        });

        return ['locked' => true, 'correct' => $correct, 'points' => $points];
    }

    /**
     * What this person's screen needs, in the shape the live games use: lobby (not started yet), question, reveal (the answer, for a moment), finished.
     *
     * @return array<string, mixed>
     */
    protected function selfState(QuizSession $session, ?User $viewer): array
    {
        $session->refresh()->loadMissing('quiz');
        if ($session->isActive() && $session->created_at->lt(now()->subDays(self::SELF_OPEN_DAYS))) {
            $this->cancel($session);
        }

        $questions = $session->quiz->questions()->get();
        $total = $questions->count();
        $player = $viewer ? QuizPlayer::where('quiz_session_id', $session->id)->where('user_id', $viewer->id)->first() : null;
        if ($player) {
            $player = $this->selfAdvance($session, $player, $total);
        }

        $status = match (true) {
            $player?->finished_at !== null => 'finished',
            ! $session->isActive() => 'cancelled',
            ! $player?->started_at => 'lobby',
            $player->revealed_at !== null => 'reveal',
            default => 'question',
        };
        $limit = self::questionSeconds() * 1000;

        $state = [
            'status' => $status,
            'mode' => 'self',
            'pin' => $session->pin,
            'quiz' => $session->quiz->title,
            'quiz_id' => $session->quiz_id,
            'community' => $session->church_id === null,
            'is_host' => $viewer !== null && $session->host_id === $viewer->id,
            'host_name' => $session->host?->gameName(),
            'total' => $total,
            'index' => $player?->started_at ? $player->current_index : null,
            'timing' => ['question' => self::questionSeconds(), 'reveal' => round(self::FEEDBACK_MS / 1000, 1)],
            'players_count' => $session->players()->count(),
            'players' => $session->players()->with('user')->latest('id')->take(60)->get()->map(fn (QuizPlayer $p) => $p->user?->gameName() ?? $p->nickname)->all(),
            'leaderboard' => $this->selfBoard($session, $player, $total, $status === 'finished' ? 50 : 10),
        ];

        $question = in_array($status, ['question', 'reveal'], true) ? $questions->get($player->current_index) : null;
        if ($question) {
            $elapsed = (int) round($player->question_started_at->diffInMilliseconds(now(), true));
            $state['question'] = [
                'text' => $question->question,
                'options' => $question->options,
                'time_limit' => self::questionSeconds(),
                'remaining_ms' => $status === 'question' ? max(0, $limit - $elapsed) : 0,
                'answered_count' => 0,
            ];
            $mine = QuizAnswer::where('quiz_player_id', $player->id)->where('quiz_question_id', $question->id)->first();
            $state['me'] = [
                'answer' => $mine?->option,
                'correct' => $status === 'reveal' ? (bool) $mine?->is_correct : null,
                'points' => $status === 'reveal' ? (int) ($mine?->points ?? 0) : null,
            ];

            if ($status === 'reveal') {
                $counts = QuizAnswer::where('quiz_session_id', $session->id)->where('quiz_question_id', $question->id)->selectRaw('`option`, COUNT(*) c')->groupBy('option')->pluck('c', 'option');
                $state['reveal'] = ['correct' => $question->correct_option, 'counts' => collect($question->options)->keys()->map(fn ($i) => (int) ($counts[$i] ?? 0))->all()];
                $state['next_in_ms'] = max(0, self::FEEDBACK_MS - (int) round($player->revealed_at->diffInMilliseconds(now(), true)));
                $state['is_last'] = $player->current_index + 1 >= $total;
            }
        }

        if ($player) {
            $state['me'] = ($state['me'] ?? ['answer' => null]) + [
                'id' => $player->id,
                'name' => $viewer->gameName(),
                'score' => $player->score,
                'rank' => $this->selfRank($session, $player),
                'streak' => $player->streak,
                'correct_count' => $player->correct_count,
                'seconds' => round($player->total_ms / 1000, 1),
            ];
        }

        return $state;
    }

    /** One place above everyone with a better score, then more right answers, then less time. */
    protected function selfRank(QuizSession $session, QuizPlayer $me): int
    {
        return QuizPlayer::where('quiz_session_id', $session->id)->whereKeyNot($me->id)->where(fn ($q) => $q
            ->where('score', '>', $me->score)
            ->orWhere(fn ($t) => $t->where('score', $me->score)->where('correct_count', '>', $me->correct_count))
            ->orWhere(fn ($t) => $t->where('score', $me->score)->where('correct_count', $me->correct_count)->where('total_ms', '<', $me->total_ms))
            ->orWhere(fn ($t) => $t->where('score', $me->score)->where('correct_count', $me->correct_count)->where('total_ms', $me->total_ms)->where('id', '<', $me->id)))->count() + 1;
    }

    /** @return array<int, array<string, mixed>> */
    protected function selfBoard(QuizSession $session, ?QuizPlayer $me, int $total, int $limit): array
    {
        return $session->players()->with('user')->orderByDesc('score')->orderByDesc('correct_count')->orderBy('total_ms')->orderBy('id')->take($limit)->get()
            ->values()->map(fn (QuizPlayer $p, int $i) => [
                'id' => $p->id, 'rank' => $i + 1, 'name' => $p->user?->gameName() ?? $p->nickname, 'score' => $p->score, 'correct' => $p->correct_count, 'streak' => $p->streak,
                'answered' => false, 'gained' => 0, 'me' => $me?->id === $p->id,
                'done' => $p->finished_at !== null, 'at' => $p->started_at ? min($total, $p->current_index + 1) : 0,
            ])->all();
    }

    /**
     * The records of a quiz: each person's best finished run in the period, best first. Nicknames only (what the person likes to be called, else their first
     * name), never full names.
     *
     * @param  string  $period  today | week | all (a day and a week start in the app's own timezone, Monday for the week)
     * @param  string  $scope  everyone | church (only people who follow a church the viewer follows)
     * @return array<string, mixed>
     */
    public function records(Quiz $quiz, string $period, ?User $viewer, string $scope = 'everyone'): array
    {
        $from = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            default => null,
        };
        $total = $quiz->questions()->count();

        $blocked = $viewer ? UserBlock::idsAround($viewer->id) : [];
        $scope = $scope === 'church' && $viewer ? 'church' : 'everyone';
        $finished = fn () => QuizPlayer::query()
            ->join('quiz_sessions', 'quiz_sessions.id', '=', 'quiz_players.quiz_session_id')
            ->where('quiz_sessions.quiz_id', $quiz->id)->where('quiz_sessions.mode', 'self')
            ->whereNotNull('quiz_players.finished_at')
            ->when($from, fn ($q) => $q->where('quiz_players.finished_at', '>=', $from))
            ->when($blocked, fn ($q) => $q->whereNotIn('quiz_players.user_id', $blocked))
            ->when($scope === 'church', fn ($q) => $q->where(fn ($w) => $w->where('quiz_players.user_id', $viewer->id)->orWhereIn('quiz_players.user_id',
                Subscription::query()->whereIn('church_id', $viewer->memberChurchIds() ?: [0])->where('status', 'active')->select('user_id'))));

        // The best runs come first; the cap keeps a very popular quiz from loading every run ever played (the counts below are exact).
        $runs = $finished()->select('quiz_players.*')->with('user')
            ->orderByDesc('quiz_players.score')->orderByDesc('quiz_players.correct_count')->orderBy('quiz_players.total_ms')->orderBy('quiz_players.finished_at')
            ->limit(3000)->get();

        $best = $runs->groupBy('user_id')->map(function ($mine) {
            $top = $mine->sortBy([['score', 'desc'], ['correct_count', 'desc'], ['total_ms', 'asc'], ['finished_at', 'asc']])->first();
            $top->setAttribute('runs', $mine->count());

            return $top;
        })->sortBy([['score', 'desc'], ['correct_count', 'desc'], ['total_ms', 'asc'], ['finished_at', 'asc']])->values();

        $row = fn (QuizPlayer $p, int $i) => [
            'rank' => $i + 1,
            'name' => $p->user?->gameName() ?? $p->nickname,
            'score' => (int) $p->score,
            'correct' => (int) $p->correct_count,
            'total' => $total,
            'seconds' => round($p->total_ms / 1000, 1),
            'at' => $p->finished_at?->toIso8601String(),
            'plays' => (int) $p->runs,
            'me' => $viewer !== null && $p->user_id === $viewer->id,
        ];
        $rows = $best->map($row)->values();
        $mine = $rows->firstWhere('me', true);

        return [
            'quiz' => ['id' => $quiz->id, 'title' => $quiz->title, 'emoji' => $quiz->emoji, 'questions' => $total],
            'period' => in_array($period, ['today', 'week'], true) ? $period : 'all',
            'scope' => $scope,
            'since' => $from?->toIso8601String(),
            'players' => (int) $finished()->distinct()->count('quiz_players.user_id'),
            'plays' => (int) $finished()->count(),
            'rows' => $rows->take(50)->all(),
            'me' => $mine,
        ];
    }

    /** "Now" as the model stores times, with milliseconds. */
    protected function stampFor(QuizPlayer $player): string
    {
        return $player->fromDateTime(now());
    }
}
