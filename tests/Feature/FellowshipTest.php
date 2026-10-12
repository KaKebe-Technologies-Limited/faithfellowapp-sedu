<?php

use App\Models\BibleChapter;
use App\Models\Church;
use App\Models\Note;
use App\Models\PrayerRequest;
use App\Models\Quiz;
use App\Models\QuizPlayer;
use App\Models\QuizSession;
use App\Models\ReadingCircle;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanProgress;
use App\Models\User;
use App\Notifications\Content\NewChurchUpdate;
use App\Notifications\Games\QuizUpdate;
use App\Notifications\Prayer\PrayerRequestReviewed;
use App\Notifications\Prayer\PrayerRequestShared;
use App\Notifications\Prayer\PrayerRequestSubmitted;
use App\Notifications\Reading\ReadingCircleUpdate;
use App\Services\Bible\BibleService;
use App\Services\ChurchArtworkService;
use App\Services\HomeSlider;
use App\Services\ReadingCircleService;
use App\Support\Bible\Reference;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** @return array{0: Church, 1: User, 2: User, 3: User} church, admin, member, another member */
function fellowshipChurch(array $attributes = []): array
{
    $admin = member();
    $church = Church::factory()->create($attributes);
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);
    $a = member();
    $b = member();
    foreach ([$a, $b] as $u) {
        joinChurch($church, $u);
    }

    return [$church, $admin, $a, $b];
}

function joinChurch(Church $church, User $user): void
{
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);
}

function fellowshipPlan(Church $church): ReadingPlan
{
    $entries = collect(range(1, 5))->map(fn ($d) => ['day' => $d, 'passages' => 'Matthew '.$d, 'note' => null])->all();

    return $church->readingPlans()->create(['title' => 'Matthew in 5 days', 'entries' => $entries, 'days_count' => 5, 'is_published' => true]);
}

function fakeBibleApi(): void
{
    Http::fake(['bible-api.com/*' => fn ($request) => Http::response(['verses' => collect(range(1, 10))->map(fn ($v) => [
        'book_id' => 'MAT', 'chapter' => (int) last(explode('/', $request->url())), 'verse' => $v, 'text' => "Verse {$v} text.\n",
    ])->all()])]);
}

/*
|--------------------------------------------------------------------------
| Bible
|--------------------------------------------------------------------------
*/

test('bible references parse chapter ranges, verse ranges and abbreviations', function () {
    $ref = Reference::parse('Matthew 3-5');
    expect(collect($ref->segments)->pluck('chapter')->all())->toBe([3, 4, 5])
        ->and(collect($ref->segments)->pluck('book')->unique()->all())->toBe(['MAT'])
        ->and($ref->title())->toBe('Matthew 3–5');

    expect(Reference::parse('Mt 5:1-12')->segments)->toBe([['book' => 'MAT', 'chapter' => 5, 'from' => 1, 'to' => 12]])
        ->and(Reference::parse('1 Cor 13')->segments[0]['book'])->toBe('1CO')
        ->and(Reference::parse('Romans 8:28-9:5')->segments)->toBe([
            ['book' => 'ROM', 'chapter' => 8, 'from' => 28, 'to' => null],
            ['book' => 'ROM', 'chapter' => 9, 'from' => 1, 'to' => 5],
        ])
        ->and(collect(Reference::parse('Genesis 1–2; Psalm 23')->segments)->pluck('book')->all())->toBe(['GEN', 'GEN', 'PSA'])
        ->and(Reference::parse('Not a book 4'))->toBeNull();
});

test('bible passages are fetched once and then served from the chapter cache', function () {
    fakeBibleApi();
    $bible = app(BibleService::class);

    $passage = $bible->passage('Matthew 3-5', 'kjv');
    expect($passage['title'])->toBe('Matthew 3–5')
        ->and($passage['sections'])->toHaveCount(3)
        ->and($passage['sections'][0]['verses'][0])->toBe(['v' => 1, 't' => 'Verse 1 text.'])
        ->and(BibleChapter::count())->toBe(3);

    $verses = $bible->passage('Matthew 4:2-3', 'kjv')['sections'][0]['verses'];
    expect(collect($verses)->pluck('v')->all())->toBe([2, 3]);
    Http::assertSentCount(3);
});

test('members read the bible in the app and reading plans show the passage inline', function () {
    fakeBibleApi();
    [, , $member] = fellowshipChurch();

    $this->actingAs($member)->get(route('bible.read', ['MAT', 3]))->assertOk()->assertSee('Verse 10 text.');
    $this->actingAs($member)->get(route('bible.passage', ['ref' => 'Matthew 3-5', 'partial' => 1]))->assertOk()
        ->assertSee('Matthew 5')->assertSee('Verse 7 text.');
    $this->actingAs($member)->get(route('bible.passage', ['ref' => 'Nothing here']))->assertNotFound();

    $this->actingAs($member)->post(route('bible.translation'), ['translation' => 'web'])->assertRedirect();
    expect($member->fresh()->bible_translation)->toBe('web');
});

test('when the bible service is unreachable the reader shows a friendly message', function () {
    Http::fake(['bible-api.com/*' => Http::response('down', 503)]);
    [, , $member] = fellowshipChurch();

    $passage = app(BibleService::class)->passage('John 3', 'kjv');
    expect($passage['sections'][0]['verses'])->toBe([])->and($passage['sections'][0]['error'])->not->toBeNull();
    $this->actingAs($member)->get(route('bible.passage', ['ref' => 'John 3', 'partial' => 1]))->assertOk()->assertSee('could not load');
});

/*
|--------------------------------------------------------------------------
| Notes
|--------------------------------------------------------------------------
*/

test('the note taker saves one private note per reading context', function () {
    [$church, , $member, $fellow] = fellowshipChurch();

    $payload = ['context' => 'bible:JHN.3', 'reference' => 'John 3', 'url' => '/bible/JHN/3', 'church_id' => $church->id, 'body' => "Born again\n**not by works**"];
    $id = $this->actingAs($member)->putJson(route('notes.save'), $payload)->assertOk()->json('id');
    $this->actingAs($member)->putJson(route('notes.save'), [...$payload, 'body' => 'Edited'])->assertOk()->assertJson(['id' => $id]);

    expect(Note::count())->toBe(1)->and(Note::first()->body)->toBe('Edited')->and(Note::first()->church_id)->toBe($church->id);

    $this->actingAs($member)->getJson(route('notes.context', ['context' => 'bible:JHN.3']))->assertJson(['id' => $id, 'body' => 'Edited']);
    $this->actingAs($fellow)->getJson(route('notes.context', ['context' => 'bible:JHN.3']))->assertJson(['id' => null, 'body' => '']);
    $this->actingAs($fellow)->get(route('notes.show', $id))->assertNotFound();
    $this->actingAs($member)->get(route('notes.index'))->assertOk()->assertSee('John 3');
    expect($this->actingAs($member)->get(route('notes.export'))->assertOk()->streamedContent())->toContain('Edited');
});

test('notes only keep links back into the app and churches the writer belongs to', function () {
    $member = member();
    $stranger = Church::factory()->create();

    $this->actingAs($member)->putJson(route('notes.save'), [
        'context' => 'sermon:9', 'url' => 'https://evil.example/phish', 'church_id' => $stranger->id, 'body' => 'x',
    ])->assertOk();
    $this->actingAs($member)->putJson(route('notes.save'), ['context' => 'javascript:alert(1)//', 'body' => 'x'])->assertUnprocessable();

    expect(Note::first()->url)->toBeNull()->and(Note::first()->church_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Reading circles
|--------------------------------------------------------------------------
*/

test('members pair up with a code, read together and see each other’s summaries', function () {
    Notification::fake();
    [$church, , $owner, $partner] = fellowshipChurch();
    $plan = fellowshipPlan($church);

    $this->actingAs($owner)->post(route('reading-circles.store', $plan), ['name' => 'Dawn readers', 'reading_time' => '06:00', 'reading_days' => [0, 1, 2, 3, 4, 5, 6]])
        ->assertRedirect();
    $circle = ReadingCircle::sole();
    expect($circle->code)->toMatch('/^[A-HJ-NP-Z2-9]{6}$/');

    $this->actingAs($partner)->post(route('reading-circles.join'), ['code' => strtolower($circle->code)])->assertRedirect(route('reading-circles.show', $circle));
    Notification::assertSentTo($owner, ReadingCircleUpdate::class, fn ($n) => $n->kind === 'joined');

    $this->actingAs($owner)->post(route('reading-circles.complete', [$circle, 1]), ['summary' => 'Jesus is the promised King.'])->assertRedirect();
    Notification::assertSentTo($partner, ReadingCircleUpdate::class, fn ($n) => $n->kind === 'finished');
    Notification::assertNotSentTo($owner, ReadingCircleUpdate::class, fn ($n) => $n->kind === 'finished');

    $this->actingAs($partner)->post(route('reading-circles.complete', [$circle, 1]), ['summary' => 'Wise men worshipped.'])->assertRedirect();
    Notification::assertSentTo([$owner, $partner], ReadingCircleUpdate::class, fn ($n) => $n->kind === 'all_done');

    // Summaries are encrypted at rest and visible to circle members only.
    expect(DB::table('reading_circle_entries')->where('user_id', $owner->id)->value('summary'))->not->toContain('promised King');
    $this->actingAs($partner)->get(route('reading-circles.show', [$circle, 'day' => 1]))->assertOk()->assertSee('Jesus is the promised King.');

    $outsider = member();
    joinChurch($church, $outsider);
    $this->actingAs($outsider)->get(route('reading-circles.show', $circle))->assertNotFound();
    expect(ReadingPlanProgress::where('user_id', $owner->id)->where('reading_plan_id', $plan->id)->first()->completed_days)->toContain(1);
});

test('reading circles hold at most four members of the same church', function () {
    Notification::fake();
    [$church, , $owner, $second] = fellowshipChurch();
    $circle = app(ReadingCircleService::class)->create($owner, fellowshipPlan($church), ['name' => 'Four', 'reading_time' => '06:00', 'reading_days' => [1]]);

    $notInChurch = member();
    $this->actingAs($notInChurch)->post(route('reading-circles.join'), ['code' => $circle->code])->assertSessionHasErrors('code');

    app(ReadingCircleService::class)->join($second, $circle->code);
    foreach ([member(), member()] as $user) {
        joinChurch($church, $user);
        app(ReadingCircleService::class)->join($user, $circle->code);
    }
    $fifth = member();
    joinChurch($church, $fifth);
    $this->actingAs($fifth)->post(route('reading-circles.join'), ['code' => $circle->code])->assertSessionHasErrors('code');

    expect($circle->memberships()->count())->toBe(4);
});

test('everyone in a circle is reminded once at the agreed reading time', function () {
    Notification::fake();
    [$church, , $owner, $partner] = fellowshipChurch();
    $this->travelTo(now()->setTime(6, 0));
    $circle = app(ReadingCircleService::class)->create($owner, fellowshipPlan($church), ['name' => 'Dawn', 'reading_time' => now()->addMinutes(5)->format('H:i'), 'reading_days' => [now()->dayOfWeek]]);
    $circle->forceFill(['timezone' => config('app.timezone')])->save();
    app(ReadingCircleService::class)->join($partner, $circle->code);

    expect(app(ReadingCircleService::class)->sendReminders())->toBe(0); // too early

    $this->travel(6)->minutes();
    expect(app(ReadingCircleService::class)->sendReminders())->toBe(1)
        ->and(app(ReadingCircleService::class)->sendReminders())->toBe(0); // only once a day
    Notification::assertSentTo([$owner, $partner], ReadingCircleUpdate::class, fn ($n) => $n->kind === 'reminder');
});

/*
|--------------------------------------------------------------------------
| Live quizzes
|--------------------------------------------------------------------------
*/

function quizPayload(): array
{
    return ['title' => 'Bible heroes', 'description' => 'Fast answers score more', 'questions' => [
        ['question' => 'Who built the ark?', 'options' => ['Moses', 'Noah', 'Abraham', 'David'], 'correct' => 1, 'time_limit' => 20, 'points' => 1000],
        ['question' => 'How many days of rain?', 'options' => ['7', '40'], 'correct' => 1, 'time_limit' => 10, 'points' => 1000],
    ]];
}

test('admins write multiple-choice quizzes; members cannot', function () {
    [$church, $admin, $member] = fellowshipChurch();

    $this->actingAs($member)->get(route('church-admin.quizzes.index', $church))->assertForbidden();
    $this->actingAs($admin)->post(route('church-admin.quizzes.store', $church), quizPayload())->assertRedirect();

    $quiz = Quiz::sole();
    expect($quiz->questions)->toHaveCount(2)
        ->and($quiz->questions[0]->options)->toBe(['Moses', 'Noah', 'Abraham', 'David'])
        ->and($quiz->questions[0]->correct_option)->toBe(1)
        ->and($quiz->questions[0]->toArray())->not->toHaveKey('correct_option');

    $this->actingAs($admin)->get(route('church-admin.quizzes.edit', [$church, $quiz]))->assertOk()->assertSee('Who built the ark', false);

    $this->actingAs($admin)->post(route('church-admin.quizzes.store', $church), ['title' => 'Bad', 'questions' => [['question' => 'Q', 'options' => ['only one'], 'correct' => 0, 'time_limit' => 20]]])
        ->assertSessionHasErrors('questions.0.options');
});

test('a live quiz scores fast correct answers higher and congratulates the winners', function () {
    Notification::fake();
    $this->freezeTime();
    [$church, $admin, $fast, $slow] = fellowshipChurch();
    $wrong = member();
    joinChurch($church, $wrong);
    $this->actingAs($admin)->post(route('church-admin.quizzes.store', $church), quizPayload());

    $this->actingAs($admin)->post(route('church-admin.quizzes.host', [$church, Quiz::sole()]), ['notify' => 1])->assertRedirect();
    $session = QuizSession::sole();
    Notification::assertSentTo($fast, QuizUpdate::class);

    foreach ([$fast, $slow, $wrong] as $player) {
        $this->actingAs($player)->post(route('games.join'), ['pin' => $session->pin])->assertRedirect(route('games.play', $session));
    }
    $this->actingAs($admin)->getJson(route('church-admin.quizzes.session.state', [$church, $session]))->assertJson(['status' => 'lobby', 'players_count' => 3]);

    $this->actingAs($admin)->postJson(route('church-admin.quizzes.session.action', [$church, $session, 'start']))->assertJson(['status' => 'question', 'index' => 0]);
    $state = $this->actingAs($fast)->getJson(route('games.state', $session))->assertOk()->json();
    expect($state['question']['options'])->toBe(['Moses', 'Noah', 'Abraham', 'David'])->and($state)->not->toHaveKey('reveal');

    $this->actingAs($fast)->postJson(route('games.answer', $session), ['option' => 1, 'question' => 0])->assertOk();
    $this->actingAs($wrong)->postJson(route('games.answer', $session), ['option' => 0, 'question' => 0])->assertOk();
    $this->travel(10)->seconds();
    $this->actingAs($slow)->postJson(route('games.answer', $session), ['option' => 1, 'question' => 0])->assertOk();
    $this->actingAs($fast)->postJson(route('games.answer', $session), ['option' => 0, 'question' => 0])->assertOk(); // second answer ignored

    $player = fn (User $u) => QuizPlayer::where('user_id', $u->id)->first();
    expect($player($fast)->score)->toBe(1000)
        ->and($player($slow)->score)->toBe(750)
        ->and($player($wrong)->score)->toBe(0);

    $this->actingAs($admin)->postJson(route('church-admin.quizzes.session.action', [$church, $session, 'reveal']))
        ->assertJson(['status' => 'reveal', 'reveal' => ['correct' => 1, 'counts' => [1, 2, 0, 0]]]);
    $this->actingAs($fast)->getJson(route('games.state', $session))->assertJson(['me' => ['correct' => true, 'rank' => 1]]);

    // Too late for question two.
    $this->actingAs($admin)->postJson(route('church-admin.quizzes.session.action', [$church, $session, 'next']))->assertJson(['index' => 1]);
    $this->travel(12)->seconds();
    $this->actingAs($slow)->postJson(route('games.answer', $session), ['option' => 1, 'question' => 1])->assertUnprocessable();

    $this->actingAs($admin)->postJson(route('church-admin.quizzes.session.action', [$church, $session, 'next']))->assertJson(['status' => 'finished']);
    Notification::assertSentTo($fast, QuizUpdate::class, fn ($n) => $n->kind === 'winner' && $n->place === 1);
    Notification::assertSentTo($slow, QuizUpdate::class, fn ($n) => $n->kind === 'winner' && $n->place === 2);
    Notification::assertNotSentTo($wrong, QuizUpdate::class, fn ($n) => $n->kind === 'winner');

    $this->actingAs($slow)->get(route('games.scores', $session))->assertOk()->assertSee($fast->first_name);
});

test('only members of the hosting church can join a game', function () {
    [$church, $admin] = fellowshipChurch();
    $this->actingAs($admin)->post(route('church-admin.quizzes.store', $church), quizPayload());
    $this->actingAs($admin)->post(route('church-admin.quizzes.host', [$church, Quiz::sole()]));
    $session = QuizSession::sole();
    $outsider = member();

    $this->actingAs($outsider)->post(route('games.join'), ['pin' => $session->pin])->assertSessionHasErrors('pin');
    $this->actingAs($outsider)->getJson(route('games.state', $session))->assertNotFound();
    $this->actingAs($outsider)->get(route('games.index'))->assertOk();
});

/*
|--------------------------------------------------------------------------
| Prayer approval and private requests
|--------------------------------------------------------------------------
*/

test('prayer requests wait for a pastor before the church is asked to pray', function () {
    Notification::fake();
    [$church, $admin, $author, $fellow] = fellowshipChurch();

    $this->actingAs($author)->post(route('prayer.store'), ['church_id' => $church->id, 'body' => 'Healing for my mother', 'visibility' => 'church'])->assertRedirect();
    $prayer = PrayerRequest::sole();
    expect($prayer->status)->toBe('pending');
    Notification::assertSentTo($admin, PrayerRequestSubmitted::class);

    $this->actingAs($fellow)->get(route('prayer.show', $prayer))->assertForbidden();
    $this->actingAs($fellow)->get(route('prayer.index'))->assertDontSee('Healing for my mother');
    $this->actingAs($author)->get(route('prayer.show', $prayer))->assertOk()->assertSee('Healing for my mother');
    $this->actingAs($admin)->get(route('church-admin.prayer.index', $church))->assertOk()->assertSee('Healing for my mother');

    $this->actingAs($admin)->post(route('church-admin.prayer.approve', [$church, $prayer]))->assertRedirect();
    expect($prayer->fresh()->status)->toBe('open');
    Notification::assertSentTo($fellow, PrayerRequestShared::class);
    Notification::assertNotSentTo($author, PrayerRequestShared::class);
    Notification::assertSentTo($author, PrayerRequestReviewed::class);

    $this->actingAs($fellow)->get(route('prayer.index'))->assertSee('Healing for my mother');
    $this->actingAs($fellow)->postJson(route('prayer.pray', $prayer))->assertOk();
});

test('declined requests never reach the wall', function () {
    Notification::fake();
    [$church, $admin, $author, $fellow] = fellowshipChurch();
    $prayer = $church->prayerRequests()->create(['user_id' => $author->id, 'body' => 'Spam spam', 'visibility' => 'church', 'status' => 'pending']);

    $this->actingAs($admin)->post(route('church-admin.prayer.decline', [$church, $prayer]))->assertRedirect();

    expect($prayer->fresh()->status)->toBe('declined');
    Notification::assertNotSentTo($fellow, PrayerRequestShared::class);
    $this->actingAs($fellow)->get(route('prayer.show', $prayer))->assertForbidden();
});

test('pastor-only requests get a private reply only the author can read', function () {
    Notification::fake();
    [$church, $admin, $author, $fellow] = fellowshipChurch();
    $this->actingAs($author)->post(route('prayer.store'), ['church_id' => $church->id, 'body' => 'A private struggle', 'visibility' => 'pastor']);
    $prayer = PrayerRequest::sole();

    $this->actingAs($admin)->post(route('church-admin.prayer.respond', [$church, $prayer]), ['response' => 'Come and see me on Sunday.'])->assertRedirect();

    Notification::assertSentTo($author, PrayerRequestReviewed::class);
    expect(DB::table('prayer_requests')->value('pastor_response'))->not->toContain('Sunday')
        ->and($prayer->fresh()->toArray())->not->toHaveKey('pastor_response');
    $this->actingAs($author)->get(route('prayer.show', $prayer))->assertOk()->assertSee('Come and see me on Sunday.');
    $this->actingAs($fellow)->get(route('prayer.show', $prayer))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Subscriptions, contact privacy and update notifications
|--------------------------------------------------------------------------
*/

test('members can follow at most three churches at a time', function () {
    $member = member();
    $churches = Church::factory()->count(4)->create();

    foreach ($churches->take(3) as $church) {
        $this->actingAs($member)->post(route('churches.subscribe', $church))->assertSessionHasNoErrors();
    }
    $this->actingAs($member)->post(route('churches.subscribe', $churches[3]))->assertSessionHas('error');
    expect($member->subscriptions()->count())->toBe(3);

    $this->actingAs($member)->get(route('churches.show', $churches[3]))->assertSee('Limit reached');

    $this->actingAs($member)->delete(route('churches.unsubscribe', $churches[0]));
    $this->actingAs($member)->post(route('churches.subscribe', $churches[3]))->assertSessionHasNoErrors();
    expect($member->subscriptions()->where('status', 'active')->count())->toBe(3);
});

test('a church’s phone and email are only shown to its subscribers and staff', function () {
    [$church, $admin, $subscriber] = fellowshipChurch(['phone' => '0772555123', 'email' => 'office@grace.test']);
    $stranger = member();

    $this->get(route('churches.show', [$church, 'tab' => 'contact']))->assertOk()->assertDontSee('0772555123')->assertDontSee('office@grace.test');
    $this->actingAs($stranger)->get(route('churches.show', [$church, 'tab' => 'contact']))->assertOk()
        ->assertDontSee('0772555123')->assertSee('Contact details are for members');
    $this->actingAs($subscriber)->get(route('churches.show', [$church, 'tab' => 'contact']))->assertSee('0772555123')->assertSee('office@grace.test');
    $this->actingAs($admin)->get(route('churches.show', [$church, 'tab' => 'contact']))->assertSee('0772555123');

    expect($church->contactsVisibleTo(null))->toBeFalse()->and($church->contactsVisibleTo($subscriber))->toBeTrue();
});

test('new church updates reach followers as in-app notifications', function () {
    Notification::fake();
    [$church, $admin, $member] = fellowshipChurch();

    $this->actingAs($admin)->post(route('church-admin.posts.store', $church), ['body' => 'Choir practice moves to Thursday.', 'visibility' => 'members'])->assertRedirect();

    Notification::assertSentTo($member, NewChurchUpdate::class);
    Notification::assertNotSentTo($admin, NewChurchUpdate::class);
});

test('the bell exposes the newest notification for the in-app pop-up', function () {
    [$church, $admin, $member] = fellowshipChurch();

    $this->actingAs($admin)->post(route('church-admin.posts.store', $church), ['body' => 'Choir practice moves to Thursday.', 'visibility' => 'members']);

    $this->actingAs($member)->getJson(route('notifications.count'))->assertOk()
        ->assertJsonPath('unread', 1)->assertJsonPath('latest.church', $church->name);
});

/*
|--------------------------------------------------------------------------
| Home slider and church artwork
|--------------------------------------------------------------------------
*/

test('admins upload slider photos and the home slider takes turns between churches', function () {
    Storage::fake('public');
    [$a, $adminA, $member] = fellowshipChurch(['name' => 'Alpha Church']);
    [$b, $adminB] = fellowshipChurch(['name' => 'Beta Church']);
    joinChurch($b, $member);
    $photos = fn ($n) => collect(range(1, $n))->map(fn ($i) => UploadedFile::fake()->image("slide{$i}.jpg", 1600, 700))->all();

    $this->actingAs($adminA)->post(route('church-admin.slides.store', $a), ['slides' => $photos(4), 'title' => 'Welcome'])->assertSessionHasNoErrors();
    $this->actingAs($adminB)->post(route('church-admin.slides.store', $b), ['slides' => $photos(3)])->assertSessionHasNoErrors();
    $this->actingAs($adminB)->post(route('church-admin.slides.store', $b), ['slides' => [UploadedFile::fake()->image('tiny.jpg', 300, 200)]])->assertSessionHasErrors('slides.0');

    expect($a->fresh()->hasSlider())->toBeTrue()->and($b->fresh()->hasSlider())->toBeFalse(); // needs four

    $this->actingAs($adminB)->post(route('church-admin.slides.store', $b), ['slides' => $photos(1)]);
    $order = app(HomeSlider::class)->forUser($member)->pluck('church')->all();
    expect($order)->toBe(['Alpha Church', 'Beta Church', 'Alpha Church', 'Beta Church', 'Alpha Church', 'Beta Church', 'Alpha Church', 'Beta Church']);

    $this->actingAs($member)->get(route('dashboard'))->assertOk()->assertSee('ff-home-slider', false);
});

test('generated artwork gives a church a square logo, a banner and four or more slides', function () {
    Storage::fake('public');
    $church = Church::factory()->create(['name' => 'Hope Chapel Gulu', 'brand_color' => '#0E8A8A']);
    $cache = storage_path('framework/testing/artwork-'.uniqid());

    app(ChurchArtworkService::class)->apply($church, cacheDir: $cache);
    $church->refresh();

    $logo = $church->getFirstMedia('logo');
    [$w, $h] = getimagesize($logo->getPath());
    expect($w)->toBe($h)
        ->and($church->getFirstMedia('cover'))->not->toBeNull()
        ->and($church->slides()->count())->toBeGreaterThanOrEqual(4)
        ->and($church->hasSlider())->toBeTrue();

    File::deleteDirectory($cache);
})->group('slow');
