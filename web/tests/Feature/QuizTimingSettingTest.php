<?php

use App\Models\PlatformSetting;
use App\Models\Quiz;
use App\Services\QuizService;
use App\Support\SampleGames;
use Laravel\Sanctum\Sanctum;

/*
| The platform admin sets how long each quiz question stays open and how long the answer shows (Super admin → Settings). Games read it as they run, and the
| app's countdown follows the game's own state, so a change shows in the next question.
*/

beforeEach(function () {
    SampleGames::install();
    Cache::forget('platform_settings');
});

test('the defaults are 15 seconds for a question and 5 for the answer', function () {
    expect(QuizService::questionSeconds())->toBe(15)->and(QuizService::revealSeconds())->toBe(5);
});

test('the super admin sets the times, and they are kept within what a game can use', function () {
    $this->actingAs(superAdmin())->put(route('super-admin.settings.update'), ['quiz_question_seconds' => 25, 'quiz_reveal_seconds' => 8])->assertSessionHasNoErrors();
    expect(QuizService::questionSeconds())->toBe(25)->and(QuizService::revealSeconds())->toBe(8);

    // Out of range is refused with a message, and the old times stay.
    $this->actingAs(superAdmin())->put(route('super-admin.settings.update'), ['quiz_question_seconds' => 2, 'quiz_reveal_seconds' => 8])->assertSessionHasErrors('quiz_question_seconds');
    $this->actingAs(superAdmin())->put(route('super-admin.settings.update'), ['quiz_question_seconds' => 500, 'quiz_reveal_seconds' => 8])->assertSessionHasErrors('quiz_question_seconds');
    $this->actingAs(superAdmin())->put(route('super-admin.settings.update'), ['quiz_question_seconds' => 20, 'quiz_reveal_seconds' => 99])->assertSessionHasErrors('quiz_reveal_seconds');
    expect(QuizService::questionSeconds())->toBe(25);

    // Empty goes back to the server's default.
    $this->actingAs(superAdmin())->put(route('super-admin.settings.update'), ['quiz_question_seconds' => '', 'quiz_reveal_seconds' => ''])->assertSessionHasNoErrors();
    expect(QuizService::questionSeconds())->toBe(15)->and(QuizService::revealSeconds())->toBe(5);

    // A value saved out of range some other way is still held to the limits.
    PlatformSetting::put('quiz_question_seconds', '1');
    PlatformSetting::put('quiz_reveal_seconds', '400');
    expect(QuizService::questionSeconds())->toBe(5)->and(QuizService::revealSeconds())->toBe(30);
});

test('a running game uses the set time, and so does the countdown the app shows', function () {
    PlatformSetting::put('quiz_question_seconds', '30');
    PlatformSetting::put('quiz_reveal_seconds', '7');
    $host = member();
    Sanctum::actingAs($host, ['member']);
    $game = $this->postJson(route('api.v1.games.start'), ['quiz_id' => Quiz::samples()->orderBy('id')->first()->id])->assertCreated()->json('data');

    $state = $this->postJson(route('api.v1.games.begin', $game['id']))->assertOk()->json('data');

    expect($state['status'])->toBe('question')->and($state['question']['time_limit'])->toBe(30)->and($state['question']['remaining_ms'])->toBeBetween(29000, 30000);
});
