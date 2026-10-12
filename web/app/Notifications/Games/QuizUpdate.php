<?php

namespace App\Notifications\Games;

use App\Models\QuizSession;
use App\Notifications\ChurchNotification;

/**
 * "A live quiz is starting — join now" and "You won!" notifications.
 *
 * @param  'starting'|'winner'  $kind
 */
class QuizUpdate extends ChurchNotification
{
    public string $category = 'games';

    public function __construct(public QuizSession $session, public string $kind, public ?int $place = null, public ?int $score = null)
    {
        $this->churchId = $session->church_id;
    }

    public function title(): string
    {
        $title = $this->session->quiz?->title;

        return $this->kind === 'winner'
            ? __('🏆 You came :place in :quiz!', ['place' => [1 => __('1st'), 2 => __('2nd'), 3 => __('3rd')][$this->place] ?? $this->place, 'quiz' => $title])
            : __('🎮 Live quiz starting: :quiz', ['quiz' => $title]);
    }

    public function body(): ?string
    {
        return $this->kind === 'winner'
            ? __('Final score: :score points. Well done!', ['score' => number_format((int) $this->score)])
            : __('Join now with game PIN :pin and play along with your church family.', ['pin' => $this->session->pin]);
    }

    public function url(): string
    {
        return $this->kind === 'winner' ? route('games.scores', $this->session) : route('games.index', ['pin' => $this->session->pin]);
    }

    public function icon(): string
    {
        return $this->kind === 'winner' ? 'bi-trophy' : 'bi-controller';
    }

    public function churchName(): ?string
    {
        return $this->session->church?->name;
    }
}
