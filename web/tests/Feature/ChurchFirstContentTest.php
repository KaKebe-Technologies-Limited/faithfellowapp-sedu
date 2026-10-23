<?php

use App\Models\Church;
use App\Models\Quiz;
use App\Models\RadioStation;
use App\Models\RadioTrack;
use App\Support\SampleGames;
use Laravel\Sanctum\Sanctum;

/*
| The app belongs to the church: what the member's own church has put on is what shows and plays first, and what FaithFellows itself has loaded (the
| Gospel and Podcast radios, the ready-made games, the public sermons of every church) fills in when the church has nothing yet.
*/

function followedBy(Church $church, $user = null)
{
    $user ??= member();
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);

    return $user;
}

function sermonFor(Church $church, string $title, string $visibility = 'public'): void
{
    $church->sermons()->create(['title' => $title, 'language' => 'en', 'status' => 'published', 'visibility' => $visibility, 'published_at' => now()->subDay(), 'preacher' => 'Pastor '.$title]);
}

function churchQuiz(Church $church, string $title = 'Our church quiz'): Quiz
{
    $quiz = Quiz::create(['church_id' => $church->id, 'title' => $title]);
    foreach (range(1, 3) as $i) {
        $quiz->questions()->create(['position' => $i, 'question' => "Question {$i}?", 'options' => ['A', 'B', 'C', 'D'], 'correct_option' => 1, 'time_limit' => 15, 'points' => 1000]);
    }

    return $quiz;
}

// ── Sermons ─────────────────────────────────────────────────────────────────────────────────────────────

test('a member\'s own church sermons are what the Sermons page shows, and other churches\' sermons are not mixed in', function () {
    $mine = Church::factory()->create();
    $other = Church::factory()->create();
    sermonFor($mine, 'Ours');
    sermonFor($other, 'Theirs');
    Sanctum::actingAs(followedBy($mine), ['member']);

    $data = $this->getJson(route('api.v1.sermons.browse'))->assertOk()->json('data');

    expect(collect($data['latest'])->pluck('title')->all())->toBe(['Ours'])->and($data['general'])->toBeFalse();
});

test('when the member\'s church has no sermons yet, the public sermons of every church fill the page, on Sermons and on Home', function () {
    $mine = Church::factory()->create();
    $other = Church::factory()->create();
    sermonFor($other, 'Public elsewhere');
    sermonFor($other, 'Members only elsewhere', 'members');
    Sanctum::actingAs(followedBy($mine), ['member']);

    $data = $this->getJson(route('api.v1.sermons.browse'))->assertOk()->json('data');
    expect(collect($data['latest'])->pluck('title')->all())->toBe(['Public elsewhere'])->and($data['general'])->toBeTrue();

    expect(collect($this->getJson(route('api.v1.home'))->assertOk()->json('data.sermons'))->pluck('title')->all())->toBe(['Public elsewhere']);

    // A member who follows no church at all gets the same.
    Sanctum::actingAs(member(), ['member']);
    expect($this->getJson(route('api.v1.sermons.browse'))->json('data.general'))->toBeTrue();
});

// ── Games ───────────────────────────────────────────────────────────────────────────────────────────────

test('the church\'s own quizzes come first in the games library, and its members can play them', function () {
    SampleGames::install();
    $church = Church::factory()->create(['name' => 'Grace Church']);
    $quiz = churchQuiz($church);
    churchQuiz($church, 'Empty one')->questions()->delete();
    $member = followedBy($church);
    Sanctum::actingAs($member, ['member']);

    $library = $this->getJson(route('api.v1.games.library'))->assertOk()->json('data');
    expect($library['categories'][0])->toMatchArray(['key' => 'my-church', 'label' => 'From your church'])
        ->and($library['quizzes'][0])->toMatchArray(['id' => $quiz->id, 'title' => 'Our church quiz', 'category' => 'my-church', 'church' => 'Grace Church', 'questions' => 3])
        ->and(collect($library['quizzes'])->pluck('title')->all())->not->toContain('Empty one')
        ->and(count($library['quizzes']))->toBe(26);

    $game = $this->postJson(route('api.v1.games.start'), ['quiz_id' => $quiz->id])->assertCreated()->json('data');
    $this->postJson(route('api.v1.games.begin', $game['id']))->assertOk()->assertJsonPath('data.status', 'question')->assertJsonPath('data.total', 3);
    $this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id, 'period' => 'all']))->assertOk()->assertJsonPath('data.quiz.title', 'Our church quiz');
});

test('a church\'s quiz is not offered to, and cannot be played by, people who do not follow the church', function () {
    SampleGames::install();
    $church = Church::factory()->create();
    $quiz = churchQuiz($church);
    Sanctum::actingAs(member(), ['member']);

    $library = $this->getJson(route('api.v1.games.library'))->assertOk()->json('data');
    expect(collect($library['categories'])->pluck('key')->all())->not->toContain('my-church')->and(count($library['quizzes']))->toBe(25);
    $this->postJson(route('api.v1.games.start'), ['quiz_id' => $quiz->id])->assertNotFound();
    $this->getJson(route('api.v1.games.records', ['quiz_id' => $quiz->id]))->assertNotFound();
});

// ── The church page ─────────────────────────────────────────────────────────────────────────────────────

test('the church page carries its songs radio next to its podcast channel', function () {
    Storage::fake('local');
    $church = Church::factory()->create();
    $follower = followedBy($church);
    $station = RadioStation::forChurch($church, 'music');
    RadioTrack::create(['station_id' => $station->id, 'title' => 'Our Song', 'file_path' => 'radio/x.mp3', 'mime' => 'audio/mpeg', 'size' => 100, 'duration_seconds' => 120, 'position' => 1, 'is_active' => true]);

    Sanctum::actingAs($follower, ['member']);
    $page = $this->getJson(route('api.v1.churches.page', $church))->assertOk()->json('data');

    expect($page['radio'])->toMatchArray(['slug' => 'church-'.$church->id.'-songs', 'songs' => 1, 'can_listen' => true])->and($page['radio']['tracks'][0]['title'])->toBe('Our Song')
        ->and($page['channel'])->toBeNull();

    // Somebody who does not follow it sees that the radio exists but gets no songs to play.
    Sanctum::actingAs(member(), ['member']);
    $page = $this->getJson(route('api.v1.churches.page', $church))->json('data');
    expect($page['radio'])->toMatchArray(['can_listen' => false, 'tracks' => []]);
});
