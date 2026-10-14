<?php

use App\Models\Church;
use App\Models\Connection;
use App\Models\Quiz;
use App\Models\QuizPlayer;
use App\Models\QuizSession;
use App\Models\UserBlock;
use App\Notifications\Games\GameInvite;
use App\Support\SampleGames;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/*
| Community games: 25 ready-made quizzes that any member can start, share by PIN and invite the people they have played with to.
*/

beforeEach(fn () => SampleGames::install());

function gamer(): array
{
    $user = member();
    Sanctum::actingAs($user, ['member']);

    return [$user, Quiz::samples()->orderBy('id')->first()];
}

test('there are 25 sample games of ten questions each, every question with four answers and one right one', function () {
    $games = SampleGames::all();
    expect($games)->toHaveCount(25);
    foreach ($games as $game) {
        expect($game['questions'])->toHaveCount(10);
        foreach ($game['questions'] as [$question, $right, $wrong]) {
            expect($wrong)->toHaveCount(3)->and(array_merge([$right], $wrong))->toHaveCount(4)->and(count(array_unique(array_merge([$right], $wrong))))->toBe(4)
                ->and(mb_strlen($question))->toBeLessThanOrEqual(300);
        }
    }

    // They are installed once (by the migration on a real site), and installing again makes no copies.
    expect(Quiz::samples()->count())->toBe(25)->and(SampleGames::install())->toBe(0)->and(Quiz::samples()->count())->toBe(25);
    $quiz = Quiz::samples()->first()->load('questions');
    expect($quiz->questions)->toHaveCount(10)->and($quiz->questions->every(fn ($q) => count($q->options) === 4 && $q->correct_option >= 0 && $q->correct_option <= 3))->toBeTrue();
    // The right answers are spread over all four positions.
    expect(Quiz::samples()->get()->flatMap->questions->pluck('correct_option')->unique()->count())->toBe(4);
});

test('the library lists the games by category', function () {
    gamer();
    $data = $this->getJson(route('api.v1.games.library'))->assertOk()->json('data');

    expect($data['quizzes'])->toHaveCount(25)->and(collect($data['categories'])->pluck('key')->all())->toContain('old-testament', 'jesus', 'uganda')
        ->and($data['quizzes'][0])->toHaveKeys(['id', 'title', 'description', 'category', 'emoji', 'questions', 'plays']);
});

test('any member can start a game, becomes its host and a player, and gets a PIN to share', function () {
    [$host, $quiz] = gamer();

    $game = $this->postJson(route('api.v1.games.start'), ['quiz_id' => $quiz->id])->assertCreated()->json('data');
    $session = QuizSession::findOrFail($game['id']);

    expect($game['pin'])->toMatch('/^\d{6}$/')->and($session->church_id)->toBeNull()->and($session->host_id)->toBe($host->id)->and($session->status)->toBe('lobby')
        ->and(QuizPlayer::where('quiz_session_id', $session->id)->where('user_id', $host->id)->exists())->toBeTrue();

    // Starting another game replaces the one still waiting.
    $second = $this->postJson(route('api.v1.games.start'), ['quiz_id' => $quiz->id])->assertCreated()->json('data');
    expect($session->fresh()->status)->toBe('cancelled')->and($second['id'])->not->toBe($session->id);

    // A church's own quiz cannot be started by just anyone.
    $other = Quiz::create(['church_id' => Church::factory()->create()->id, 'title' => 'Church quiz']);
    $this->postJson(route('api.v1.games.start'), ['quiz_id' => $other->id])->assertNotFound();
});

test('anyone with the PIN can join a community game, play it and see it in the lobby', function () {
    [$host, $quiz] = gamer();
    $game = $this->postJson(route('api.v1.games.start'), ['quiz_id' => $quiz->id])->json('data');

    $guest = member(['name' => 'Grace Akello']);
    Sanctum::actingAs($guest, ['member']);
    $this->postJson(route('api.v1.games.join'), ['pin' => $game['pin']])->assertOk()->assertJsonPath('data.id', $game['id']);

    $state = $this->getJson(route('api.v1.games.state', $game['id']))->assertOk()->json('data');
    expect($state['status'])->toBe('lobby')->and($state['community'])->toBeTrue()->and($state['is_host'])->toBeFalse()->and($state['players_count'])->toBe(2)->and($state['players'])->toContain('Grace Akello');

    // A stranger who is not in the game cannot look at it by number.
    Sanctum::actingAs(member(), ['member']);
    $this->getJson(route('api.v1.games.state', $game['id']))->assertNotFound();
    $this->postJson(route('api.v1.games.join'), ['pin' => '000000'])->assertUnprocessable();
});

test('only the host starts the game, and everyone who joined or was invited is told', function () {
    Notification::fake();
    [$host, $quiz] = gamer();
    $game = $this->postJson(route('api.v1.games.start'), ['quiz_id' => $quiz->id])->json('data');

    $joined = member();
    $invited = member();
    $session = QuizSession::findOrFail($game['id']);
    $session->invites()->create(['user_id' => $invited->id, 'invited_by' => $host->id]);
    Sanctum::actingAs($joined, ['member']);
    $this->postJson(route('api.v1.games.join'), ['pin' => $game['pin']])->assertOk();

    $this->postJson(route('api.v1.games.begin', $game['id']))->assertNotFound(); // not the host

    Sanctum::actingAs($host, ['member']);
    $state = $this->postJson(route('api.v1.games.begin', $game['id']))->assertOk()->json('data');

    expect($state['status'])->toBe('question')->and($state['is_host'])->toBeTrue();
    Notification::assertSentTo([$joined, $invited], GameInvite::class, fn ($n) => $n->kind === 'started');
    Notification::assertNotSentTo($host, GameInvite::class);

    $this->postJson(route('api.v1.games.begin', $game['id']))->assertUnprocessable(); // already started
});

test('the people you have played with, and your connections, can be invited by name; strangers and blocked members cannot', function () {
    Notification::fake();
    [$host, $quiz] = gamer();
    $first = $this->postJson(route('api.v1.games.start'), ['quiz_id' => $quiz->id])->json('data');

    $friend = member(['name' => 'Peter Opio']);
    $connected = member(['name' => 'Ruth Adong']);
    $stranger = member(['name' => 'Stranger Danger']);
    $blocked = member(['name' => 'Blocked Person']);
    Connection::create(['requester_id' => $host->id, 'addressee_id' => $connected->id, 'status' => 'accepted']);

    // Peter and the blocked member play the first game with the host.
    foreach ([$friend, $blocked] as $guest) {
        Sanctum::actingAs($guest, ['member']);
        $this->postJson(route('api.v1.games.join'), ['pin' => $first['pin']])->assertOk();
    }
    UserBlock::create(['user_id' => $host->id, 'blocked_id' => $blocked->id]);

    // A second game: the friends list shows who can be tapped.
    Sanctum::actingAs($host, ['member']);
    $second = $this->postJson(route('api.v1.games.start'), ['quiz_id' => $quiz->id])->json('data');
    $people = collect($this->getJson(route('api.v1.games.friends', ['session' => $second['id']]))->assertOk()->json('data'));

    expect($people->pluck('name')->all())->toContain('Peter Opio', 'Ruth Adong')->not->toContain('Stranger Danger', 'Blocked Person')
        ->and($people->firstWhere('name', 'Peter Opio')['plays'])->toBe(1)->and($people->firstWhere('name', 'Peter Opio')['invited'])->toBeFalse();

    $this->postJson(route('api.v1.games.invite', $second['id']), ['user_ids' => [$friend->id, $connected->id, $stranger->id, $blocked->id]])
        ->assertOk()->assertJson(['invited' => 2, 'skipped' => 2]);
    Notification::assertSentTo([$friend, $connected], GameInvite::class, fn ($n) => $n->kind === 'invite' && $n->from->is($host));
    Notification::assertNotSentTo($stranger, GameInvite::class);

    // Inviting twice does not buzz them twice, and the list now says who was invited.
    $this->postJson(route('api.v1.games.invite', $second['id']), ['user_ids' => [$friend->id]])->assertOk()->assertJson(['invited' => 0]);
    expect(collect($this->getJson(route('api.v1.games.friends', ['session' => $second['id']]))->json('data'))->firstWhere('name', 'Peter Opio')['invited'])->toBeTrue();

    // The invited member sees the invitation, can open the game from it and is put in.
    Sanctum::actingAs($friend, ['member']);
    $invites = $this->getJson(route('api.v1.games.index'))->assertOk()->json('data.invites');
    expect($invites)->toHaveCount(1)->and($invites[0]['session_id'])->toBe($second['id'])->and($invites[0]['from'])->toBe($host->name);
    $this->getJson(route('api.v1.games.state', $second['id']))->assertOk();
    expect(QuizPlayer::where('quiz_session_id', $second['id'])->where('user_id', $friend->id)->exists())->toBeTrue();

    // Only the host can invite.
    $this->postJson(route('api.v1.games.invite', $second['id']), ['user_ids' => [$stranger->id]])->assertNotFound();
});

test('a community game runs to the end by itself and lists the winner', function () {
    [$host, $quiz] = gamer();
    $game = $this->postJson(route('api.v1.games.start'), ['quiz_id' => $quiz->id])->json('data');
    $this->postJson(route('api.v1.games.begin', $game['id']))->assertOk();

    $state = $this->getJson(route('api.v1.games.state', $game['id']))->assertOk()->json('data');
    expect($state['status'])->toBe('question')->and($state['total'])->toBe(10)->and($state['question']['options'])->toHaveCount(4);

    $correct = $quiz->questions()->first()->correct_option;
    $this->postJson(route('api.v1.games.answer', $game['id']), ['option' => $correct, 'question' => 0])->assertOk();
    $after = $this->getJson(route('api.v1.games.state', $game['id']))->json('data');
    expect($after['status'])->toBe('reveal')->and($after['me']['score'])->toBeGreaterThan(0);

    // The host can close the game.
    $this->postJson(route('api.v1.games.cancel', $game['id']))->assertOk();
    expect(QuizSession::find($game['id'])->status)->toBe('cancelled');
});
