<?php

namespace App\Notifications\Games;

use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A community game: "Grace invited you to play" and "The game has started". Shown in the member's notification list in
 * the app (a phone push goes with it, see QuizService), and tapping it opens the game.
 *
 * @param  'invite'|'started'  $kind
 */
class GameInvite extends Notification
{
    use Queueable;

    public function __construct(public QuizSession $session, public string $kind, public ?User $from = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function title(): string
    {
        $quiz = $this->session->quiz?->title;

        return $this->kind === 'started'
            ? __('🎮 The game has started: :quiz', ['quiz' => $quiz])
            : __('🎮 :name invited you to play :quiz', ['name' => $this->from?->name ?? __('Someone'), 'quiz' => $quiz]);
    }

    public function body(): string
    {
        return $this->kind === 'started'
            ? __('Jump in now and answer fast!')
            : __('Tap to join, or enter the game PIN :pin.', ['pin' => $this->session->pin]);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'icon' => 'bi-controller',
            'url' => route('games.play', $this->session),
            'kind' => 'game',
            'ref' => $this->session->id,
        ];
    }
}
