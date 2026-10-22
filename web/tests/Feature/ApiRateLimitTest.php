<?php

use App\Models\Quiz;
use App\Notifications\Auth\PasswordResetCode;
use App\Support\SampleGames;
use Laravel\Sanctum\Sanctum;

/*
| The app's allowance is counted for each person, not for each address: a church's Wi-Fi or a mobile network puts many phones behind one address, and
| counting them together shut whole congregations out ("Too many attempts"). A game screen asks the server every second, which must never use up the allowance.
*/

test('two people on the same address each have their own allowance', function () {
    $ann = member();
    $bob = member();

    Sanctum::actingAs($ann, ['member']);
    foreach (range(1, 240) as $i) {
        $this->getJson(route('api.v1.me'))->assertOk();
    }
    $this->getJson(route('api.v1.me'))->assertStatus(429);

    // Bob is on the same address (the tests all come from one) and is not affected by Ann's.
    Sanctum::actingAs($bob, ['member']);
    $this->getJson(route('api.v1.me'))->assertOk();
});

test('the game screen can ask for the game every second for a whole game without being stopped', function () {
    SampleGames::install();
    $host = member();
    Sanctum::actingAs($host, ['member']);
    $game = $this->postJson(route('api.v1.games.start'), ['quiz_id' => Quiz::samples()->orderBy('id')->first()->id])->assertCreated()->json('data');

    // 250 polls, more than four minutes of one a second, and the Start button still works after them.
    foreach (range(1, 250) as $i) {
        $this->getJson(route('api.v1.games.state', $game['id']))->assertOk();
    }
    $this->postJson(route('api.v1.games.begin', $game['id']))->assertOk()->assertJsonPath('data.status', 'question');
    $this->getJson(route('api.v1.me'))->assertOk();
});

test('a password code is sent once a minute for an address, whoever asks', function () {
    Notification::fake();
    $user = member(['email' => 'mary@example.test']);

    $this->postJson(route('api.v1.auth.password.code'), ['email' => 'mary@example.test'])->assertAccepted();
    $this->postJson(route('api.v1.auth.password.code'), ['email' => 'Mary@Example.test'])->assertAccepted();

    Notification::assertSentToTimes($user, PasswordResetCode::class, 1);
    $this->travel(61)->seconds();
    $this->postJson(route('api.v1.auth.password.code'), ['email' => 'mary@example.test'])->assertAccepted();
    Notification::assertSentToTimes($user, PasswordResetCode::class, 2);
});
