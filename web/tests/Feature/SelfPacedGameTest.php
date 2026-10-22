<?php

use App\Models\Church;
use App\Models\Quiz;
use App\Models\QuizPlayer;
use App\Models\QuizSession;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\SampleGames;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/*
| Games members start themselves are played at each person's own pace: whoever joins, even days later, starts at the first question; answering moves on
| at once; a question nobody answers closes by itself. Every finished run is a record, ranked for today, this week and all time by the name each person
| likes to be called.
*/

beforeEach(function () {
    SampleGames::install();
    Cache::forget('platform_settings');
});

/** The first ready-made quiz and a game of it started by $host. @return array{0: Quiz, 1: array<string, mixed>} */
function startedGame(User $host): array
{
    $quiz = Quiz::samples()->orderBy('id')->first();
    Sanctum::actingAs($host, ['member']);

    return [$quiz, test()->postJson(route('api.v1.games.start'), ['quiz_id' => $quiz->id])->assertCreated()->json('data')];
}

/** Plays a whole run as $user: $right questions answered correctly (the first ones), the rest wrongly, $wait seconds after each question appears. */
function playRun(User $user, Quiz $quiz, array $game, int $right = 10, int $wait = 0): array
{
    Sanctum::actingAs($user, ['member']);
    $test = test();
    $test->postJson(route('api.v1.games.join'), ['pin' => $game['pin']])->assertOk();
    $state = $test->postJson(route('api.v1.games.begin', $game['id']))->assertOk()->json('data');

    foreach ($quiz->questions()->orderBy('id')->get() as $i => $question) {
        expect($state['status'])->toBe('question')->and($state['index'])->toBe($i);
        $test->travel($wait)->seconds();
        $option = $i < $right ? $question->correct_option : ($question->correct_option + 1) % 4;
        $state = $test->postJson(route('api.v1.games.answer', $game['id']), ['option' => $option, 'question' => $i])->assertOk()->json('data');
        expect($state['status'])->toBe('reveal');
        $state = $test->postJson(route('api.v1.games.next', $game['id']))->assertOk()->json('data');
    }
    expect($state['status'])->toBe('finished');

    return $state;
}

test('a game of a member is self-paced and a question is open for 15 seconds', function () {
    $this->freezeSecond();
    [$quiz, $game] = startedGame($host = member());
    $session = QuizSession::findOrFail($game['id']);

    expect($session->mode)->toBe('self')->and($session->church_id)->toBeNull()->and($session->isActive())->toBeTrue();

    $state = $this->getJson(route('api.v1.games.state', $game['id']))->assertOk()->json('data');
    expect($state['status'])->toBe('lobby')->and($state['mode'])->toBe('self')->and($state['timing']['question'])->toBe(15)->and($state['total'])->toBe(10);

    $state = $this->postJson(route('api.v1.games.begin', $game['id']))->assertOk()->json('data');
    expect($state['status'])->toBe('question')->and($state['index'])->toBe(0)->and($state['question']['remaining_ms'])->toBe(15000)->and($state['question']['options'])->toHaveCount(4);
});

test('someone who joins late starts from the first question, and everyone plays at their own pace', function () {
    $this->freezeSecond();
    [$quiz, $game] = startedGame($host = member());
    $this->postJson(route('api.v1.games.begin', $game['id']))->assertOk();

    // The host plays three questions.
    foreach (range(0, 2) as $i) {
        $this->postJson(route('api.v1.games.answer', $game['id']), ['option' => $quiz->questions()->orderBy('id')->get()[$i]->correct_option, 'question' => $i])->assertOk();
        $this->postJson(route('api.v1.games.next', $game['id']))->assertOk();
    }
    expect($this->getJson(route('api.v1.games.state', $game['id']))->json('data.index'))->toBe(3);

    // A friend arrives two days later with the PIN: the game is still open, and they start at the first question with the whole time.
    $this->travel(2)->days();
    $friend = member();
    Sanctum::actingAs($friend, ['member']);
    $this->postJson(route('api.v1.games.join'), ['pin' => $game['pin']])->assertOk();
    $lobby = $this->getJson(route('api.v1.games.state', $game['id']))->json('data');
    expect($lobby['status'])->toBe('lobby');

    $state = $this->postJson(route('api.v1.games.begin', $game['id']))->assertOk()->json('data');
    expect($state['status'])->toBe('question')->and($state['index'])->toBe(0)->and($state['question']['remaining_ms'])->toBe(15000);

    // The host is still on question 4, the friend on question 1, and each sees the other's progress.
    $board = collect($state['leaderboard']);
    expect($board->firstWhere('me', false)['at'])->toBe(4)->and($board->firstWhere('me', true)['at'])->toBe(1);
});

test('answering moves straight on, without waiting for the time to run out', function () {
    $this->freezeSecond();
    [$quiz, $game] = startedGame(member());
    $this->postJson(route('api.v1.games.begin', $game['id']))->assertOk();
    $first = $quiz->questions()->orderBy('id')->first();

    // Quick and right: the answer is told in the same breath, with the points.
    $this->travel(2)->seconds();
    $answered = $this->postJson(route('api.v1.games.answer', $game['id']), ['option' => $first->correct_option, 'question' => 0])->assertOk()->json();
    expect($answered['correct'])->toBeTrue()->and($answered['points'])->toBe(933)
        ->and($answered['data']['status'])->toBe('reveal')->and($answered['data']['me']['correct'])->toBeTrue()->and($answered['data']['me']['score'])->toBe(933)
        ->and($answered['data']['reveal']['correct'])->toBe($first->correct_option)->and($answered['data']['question']['remaining_ms'])->toBe(0);

    // A moment later the next question comes by itself (with the full time on it), not 15 seconds later.
    $this->travel(1)->seconds();
    expect($this->getJson(route('api.v1.games.state', $game['id']))->json('data.status'))->toBe('reveal');
    $this->travel(2)->seconds();
    $state = $this->getJson(route('api.v1.games.state', $game['id']))->json('data');
    expect($state['status'])->toBe('question')->and($state['index'])->toBe(1)->and($state['question']['remaining_ms'])->toBe(15000);

    // "Next" skips the wait.
    $second = $quiz->questions()->orderBy('id')->get()[1];
    $this->postJson(route('api.v1.games.answer', $game['id']), ['option' => ($second->correct_option + 1) % 4, 'question' => 1])->assertOk()
        ->assertJsonPath('correct', false)->assertJsonPath('data.me.streak', 0);
    $state = $this->postJson(route('api.v1.games.next', $game['id']))->assertOk()->json('data');
    expect($state['status'])->toBe('question')->and($state['index'])->toBe(2);

    // "Next" while a question is still open does nothing: it cannot be used to skip a question unanswered.
    $state = $this->postJson(route('api.v1.games.next', $game['id']))->assertOk()->json('data');
    expect($state['status'])->toBe('question')->and($state['index'])->toBe(2);
});

test('a question nobody answers closes by itself when its 15 seconds are up', function () {
    $this->freezeSecond();
    [$quiz, $game] = startedGame(member());
    $this->postJson(route('api.v1.games.begin', $game['id']))->assertOk();

    $this->travel(15)->seconds();
    expect($this->getJson(route('api.v1.games.state', $game['id']))->json('data.status'))->toBe('question'); // a second's grace for slow networks
    $this->travel(1)->seconds();
    $state = $this->getJson(route('api.v1.games.state', $game['id']))->json('data');
    expect($state['status'])->toBe('reveal')->and($state['me']['answer'])->toBeNull()->and($state['me']['score'])->toBe(0)->and($state['reveal']['correct'])->toBe($quiz->questions()->orderBy('id')->first()->correct_option);

    // Too late to answer it now, and the next question follows.
    $this->postJson(route('api.v1.games.answer', $game['id']), ['option' => 0, 'question' => 0])->assertUnprocessable();
    $this->travel(3)->seconds();
    expect($this->getJson(route('api.v1.games.state', $game['id']))->json('data.index'))->toBe(1);
});

test('a question can be answered once, only the one on screen, and only with one of its options', function () {
    $this->freezeSecond();
    [$quiz, $game] = startedGame(member());
    $this->postJson(route('api.v1.games.answer', $game['id']), ['option' => 0, 'question' => 0])->assertUnprocessable(); // not started yet
    $this->postJson(route('api.v1.games.begin', $game['id']))->assertOk();

    $this->postJson(route('api.v1.games.answer', $game['id']), ['option' => 0, 'question' => 3])->assertUnprocessable(); // not the one on screen
    $this->postJson(route('api.v1.games.answer', $game['id']), ['option' => 0, 'question' => 0])->assertOk();
    $this->postJson(route('api.v1.games.answer', $game['id']), ['option' => 1, 'question' => 0])->assertUnprocessable(); // already answered
    expect(QuizPlayer::where('quiz_session_id', $game['id'])->first()->answers()->count())->toBe(1);
});

test('finishing a run shows the results and the board, and a run can be played again in a new game', function () {
    $this->freezeSecond();
    $host = member(['name' => 'Sedrick Otolo', 'nickname' => 'Sedu']);
    [$quiz, $game] = startedGame($host);

    $finished = playRun($host, $quiz, $game, right: 7);
    $me = collect($finished['leaderboard'])->firstWhere('me', true);
    expect($finished['me']['correct_count'])->toBe(7)->and($finished['me']['score'])->toBe(7000)->and($finished['me']['rank'])->toBe(1)
        ->and($me['name'])->toBe('Sedu')->and($me['done'])->toBeTrue()->and($finished['status'])->toBe('finished');

    // It stays finished when opened later, and the game is no longer listed as waiting for its host.
    expect($this->getJson(route('api.v1.games.state', $game['id']))->json('data.status'))->toBe('finished');
    expect($this->getJson(route('api.v1.games.index'))->json('data.hosting'))->toBe([]);
    expect(collect($this->getJson(route('api.v1.games.index'))->json('data.mine'))->first())->toMatchArray(['finished' => true, 'score' => 7000]);

    // Playing again is a new game, starting at the first question.
    $again = $this->postJson(route('api.v1.games.start'), ['quiz_id' => $quiz->id])->assertCreated()->json('data');
    expect($again['id'])->not->toBe($game['id'])
        ->and($this->postJson(route('api.v1.games.begin', $again['id']))->json('data.index'))->toBe(0);
});

test('the records rank each person by their best finished run, for today, this week and all time', function () {
    $this->travelTo(Carbon::parse('2026-03-04 10:00', 'Africa/Kampala')); // a Wednesday
    $host = member(['name' => 'Sedrick Otolo', 'nickname' => 'Sedu']);
    $pastor = member(['name' => 'Ruth Akello']);
    $guest = member(['name' => 'Peter Opio', 'nickname' => 'Peter the Fast']);
    [$quiz, $game] = startedGame($host);

    playRun($host, $quiz, $game, right: 6);          // 6000
    playRun($pastor, $quiz, $game, right: 9);        // 9000
    playRun($guest, $quiz, $game, right: 9, wait: 3); // 9 correct, a little slower
    playRun($host, $quiz, startedGame($host)[1], right: 3); // a worse second run, in a new game, does not replace the best

    // A run that was started and not finished is not a record.
    $quitter = member(['name' => 'Quitter']);
    Sanctum::actingAs($quitter, ['member']);
    $this->postJson(route('api.v1.games.join'), ['pin' => $game['pin']])->assertOk();
    $this->postJson(route('api.v1.games.begin', $game['id']))->assertOk();

    Sanctum::actingAs($host, ['member']);
    $today = $this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id, 'period' => 'today']))->assertOk()->json('data');
    expect($today['quiz'])->toMatchArray(['id' => $quiz->id, 'questions' => 10])
        ->and(collect($today['rows'])->pluck('name')->all())->toBe(['Ruth', 'Peter the Fast', 'Sedu'])  // the first name shows when no nickname is set
        ->and(collect($today['rows'])->pluck('rank')->all())->toBe([1, 2, 3])
        ->and($today['rows'][0])->toMatchArray(['score' => 9000, 'correct' => 9, 'total' => 10, 'me' => false])
        ->and($today['rows'][2])->toMatchArray(['score' => 6000, 'correct' => 6, 'plays' => 2, 'me' => true])
        ->and($today['me']['rank'])->toBe(3)->and($today['players'])->toBe(3)->and($today['plays'])->toBe(4);
    // Full names never leave the app.
    expect(json_encode($today))->not->toContain('Akello')->not->toContain('Otolo')->not->toContain('Opio');

    // Next day: nothing today, but this week and all time still show the same people.
    $this->travelTo(Carbon::parse('2026-03-05 09:00', 'Africa/Kampala'));
    expect($this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id, 'period' => 'today']))->json('data.rows'))->toBe([])
        ->and(collect($this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id, 'period' => 'week']))->json('data.rows'))->pluck('name')->all())->toBe(['Ruth', 'Peter the Fast', 'Sedu']);

    // The week starts on Monday: the next Monday begins an empty week, while all time keeps the history.
    $this->travelTo(Carbon::parse('2026-03-09 08:00', 'Africa/Kampala'));
    expect($this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id, 'period' => 'week']))->json('data.rows'))->toBe([])
        ->and($this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id, 'period' => 'all']))->json('data.rows'))->toHaveCount(3);
});

test('records can be kept to the people of your churches, and never show members you blocked', function () {
    $this->freezeSecond();
    $church = Church::factory()->create();
    $me = member(['name' => 'Me Myself']);
    $pastor = member(['name' => 'Pastor Ruth']);
    $stranger = member(['name' => 'Stranger Danger']);
    $bully = member(['name' => 'Bully Boy']);
    foreach ([$me, $pastor] as $u) {
        $church->subscriptions()->create(['user_id' => $u->id, 'status' => 'active', 'subscribed_at' => now()]);
    }
    [$quiz, $game] = startedGame($me);
    foreach ([[$me, 5], [$pastor, 8], [$stranger, 9], [$bully, 10]] as [$u, $right]) {
        playRun($u, $quiz, $game, right: $right);
    }
    UserBlock::create(['user_id' => $me->id, 'blocked_id' => $bully->id]);

    Sanctum::actingAs($me, ['member']);
    $everyone = $this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id, 'period' => 'all']))->json('data');
    $mine = $this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id, 'period' => 'all', 'scope' => 'church']))->json('data');

    expect(collect($everyone['rows'])->pluck('name')->all())->toBe(['Stranger', 'Pastor', 'Me'])
        ->and(collect($mine['rows'])->pluck('name')->all())->toBe(['Pastor', 'Me'])->and($mine['scope'])->toBe('church')->and($mine['me']['rank'])->toBe(2);

    $this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id, 'period' => 'yesterday']))->assertUnprocessable();
    $this->getJson(route('api.v1.games.records', ['quiz_id' => 999999]))->assertNotFound();
});

test('a person chooses the name they like to be called, and it is what other players see', function () {
    $user = member(['name' => 'Kakebe Technologies']);
    Sanctum::actingAs($user, ['member']);

    $this->getJson(route('api.v1.me'))->assertOk()->assertJsonPath('data.nickname', null)->assertJsonPath('data.game_name', 'Kakebe');
    $this->patchJson(route('api.v1.me.update'), ['nickname' => '  Sedu the Great '])->assertOk()->assertJsonPath('data.nickname', 'Sedu the Great')->assertJsonPath('data.game_name', 'Sedu the Great');
    expect($user->fresh()->gameName())->toBe('Sedu the Great');

    // A nickname is shown to strangers, so it cannot carry a phone number or an address, and cannot be one letter.
    $this->patchJson(route('api.v1.me.update'), ['nickname' => 'call 0772123456'])->assertUnprocessable()->assertJsonValidationErrors('nickname');
    $this->patchJson(route('api.v1.me.update'), ['nickname' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('nickname');
    $this->patchJson(route('api.v1.me.update'), ['nickname' => str_repeat('a', 31)])->assertUnprocessable();

    // Changing it changes how the person shows on boards already played; empty goes back to the first name.
    $this->freezeSecond();
    [$quiz, $game] = startedGame($user->fresh());
    playRun($user->fresh(), $quiz, $game, right: 4);
    expect($this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id, 'period' => 'all']))->json('data.rows.0.name'))->toBe('Sedu the Great');
    $this->patchJson(route('api.v1.me.update'), ['nickname' => ''])->assertOk()->assertJsonPath('data.nickname', null);
    expect($this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id, 'period' => 'all']))->json('data.rows.0.name'))->toBe('Kakebe');
});

test('a game room stays open for a week, then closes, and finished runs are still kept', function () {
    $this->freezeSecond();
    $host = member();
    [$quiz, $game] = startedGame($host);
    $friend = member();
    playRun($friend, $quiz, $game, right: 10);

    $this->travel(6)->days();
    Sanctum::actingAs(member(), ['member']);
    $this->postJson(route('api.v1.games.join'), ['pin' => $game['pin']])->assertOk(); // still open on day six

    $this->travel(2)->days();
    $this->postJson(route('api.v1.games.join'), ['pin' => $game['pin']])->assertUnprocessable(); // day eight: the PIN no longer opens the room
    Sanctum::actingAs($host, ['member']);
    expect($this->getJson(route('api.v1.games.state', $game['id']))->json('data.status'))->toBe('cancelled');
    $this->postJson(route('api.v1.games.begin', $game['id']))->assertUnprocessable();

    Sanctum::actingAs($friend, ['member']);
    expect($this->getJson(route('api.v1.games.state', $game['id']))->json('data.status'))->toBe('finished')
        ->and($this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id, 'period' => 'all']))->json('data.players'))->toBe(1);
});

test('strangers cannot start someone else\'s run, and a run of a church quiz cannot be started by a member', function () {
    $this->freezeSecond();
    [$quiz, $game] = startedGame(member());

    Sanctum::actingAs(member(), ['member']);
    $this->postJson(route('api.v1.games.begin', $game['id']))->assertNotFound();
    $this->postJson(route('api.v1.games.next', $game['id']))->assertNotFound();
    $this->postJson(route('api.v1.games.answer', $game['id']), ['option' => 0, 'question' => 0])->assertNotFound();

    $church = Quiz::create(['church_id' => Church::factory()->create()->id, 'title' => 'Church quiz']);
    $this->postJson(route('api.v1.games.start'), ['quiz_id' => $church->id])->assertNotFound();
});

test('the library tells each member their best score of a quiz', function () {
    $this->freezeSecond();
    $me = member();
    [$quiz, $game] = startedGame($me);
    playRun($me, $quiz, $game, right: 8);

    $row = collect($this->getJson(route('api.v1.games.library'))->assertOk()->json('data.quizzes'))->firstWhere('id', $quiz->id);
    $other = collect($this->getJson(route('api.v1.games.library'))->json('data.quizzes'))->firstWhere('id', '!=', $quiz->id);

    expect($row)->toMatchArray(['my_best' => 8000, 'my_runs' => 1])->and($other)->toMatchArray(['my_best' => null, 'my_runs' => 0]);
});
