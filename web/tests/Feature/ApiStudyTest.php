<?php

use App\Models\Church;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/*
| The Bible, the notebook, reading plans and reading circles in the app.
*/

/** @return array{0: User, 1: Church} a signed-in member of a church */
function studyMember(): array
{
    $member = member();
    Sanctum::actingAs($member, ['member']);
    $church = Church::factory()->create(['name' => 'Grace Lira', 'slug' => 'grace-lira']);
    $church->subscriptions()->create(['user_id' => $member->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);

    return [$member, $church];
}

function studyPlan(Church $church): ReadingPlan
{
    $entries = collect(range(1, 5))->map(fn ($d) => ['day' => $d, 'title' => "Day $d", 'passages' => 'Matthew '.$d, 'reflection' => 'Think on it.'])->all();

    return $church->readingPlans()->create(['title' => 'Matthew in 5 days', 'description' => 'A short walk through Matthew.', 'entries' => $entries, 'days_count' => 5, 'is_published' => true]);
}

function studyBible(): void
{
    Http::fake(['bible-api.com/*' => fn ($request) => Http::response(['verses' => collect(range(1, 6))->map(fn ($v) => [
        'book_id' => 'MAT', 'chapter' => (int) last(explode('/', $request->url())), 'verse' => $v, 'text' => "Verse {$v} text.\n",
    ])->all()])]);
}

// ── Bible ──────────────────────────────────────────────────────────────────────────────────────────────────

test('the Bible home lists translations, the 66 books and highlight colours', function () {
    studyMember();

    $data = $this->getJson(route('api.v1.bible.index'))->assertOk()->json('data');

    expect($data['books'])->toHaveCount(66)
        ->and($data['books'][0])->toBe(['code' => 'GEN', 'name' => 'Genesis', 'chapters' => 50, 'testament' => 'OT'])
        ->and(collect($data['translations'])->pluck('key')->all())->toContain('kjv', 'web')
        ->and($data['translation'])->toBe('kjv')
        ->and(collect($data['colors'])->pluck('key')->all())->toBe(['yellow', 'green', 'blue', 'pink'])
        ->and($data['highlights'])->toBe([]);
});

test('a chapter comes as numbered verses with the member\'s highlights and neighbouring chapters', function () {
    studyMember();
    studyBible();

    $this->postJson(route('api.v1.bible.highlight'), ['verses' => ['MAT.3.2', 'MAT.3.3'], 'color' => 'yellow'])->assertOk()->assertJson(['count' => 2]);
    $this->postJson(route('api.v1.bible.highlight'), ['verses' => ['MAT.3.3'], 'color' => 'blue'])->assertOk();

    $data = $this->getJson(route('api.v1.bible.read', ['MAT', 3]))->assertOk()->json('data');
    expect($data['book_name'])->toBe('Matthew')
        ->and($data['chapters'])->toBe(28)
        ->and($data['verses'])->toHaveCount(6)
        ->and($data['verses'][0])->toBe(['v' => 1, 't' => 'Verse 1 text.', 'color' => null])
        ->and($data['verses'][1]['color'])->toBe('yellow')
        ->and($data['verses'][2]['color'])->toBe('blue')
        ->and($data['previous'])->toBe(['book' => 'MAT', 'chapter' => 2, 'label' => 'Matthew 2'])
        ->and($data['next']['label'])->toBe('Matthew 4');

    // First chapter of a book points back to the last chapter of the previous book.
    expect($this->getJson(route('api.v1.bible.read', ['MAT', 1]))->json('data.previous.label'))->toBe('Malachi 4');

    // Clearing a highlight, and the "recent highlights" list.
    expect($this->getJson(route('api.v1.bible.highlights'))->json('data'))->toHaveCount(2);
    $this->postJson(route('api.v1.bible.highlight'), ['verses' => ['MAT.3.2'], 'color' => null])->assertOk();
    expect($this->getJson(route('api.v1.bible.highlights'))->json('data'))->toHaveCount(1);
    expect($this->getJson(route('api.v1.bible.highlights'))->json('data.0.text'))->toBe('Verse 3 text.');

    $this->getJson(route('api.v1.bible.read', ['XYZ', 1]))->assertNotFound();
    $this->getJson(route('api.v1.bible.read', ['MAT', 99]))->assertNotFound();
    $this->postJson(route('api.v1.bible.highlight'), ['verses' => ['not a verse'], 'color' => 'yellow'])->assertUnprocessable();
});

test('a reference like Matthew 3-4 opens as passages, and nonsense is not found', function () {
    studyMember();
    studyBible();

    $data = $this->getJson(route('api.v1.bible.passage', ['ref' => 'Matthew 3-4']))->assertOk()->json('data');
    expect($data['sections'])->toHaveCount(2)
        ->and($data['sections'][1]['label'])->toContain('Matthew 4')
        ->and($data['sections'][1]['verses'][0]['t'])->toBe('Verse 1 text.');

    $this->getJson(route('api.v1.bible.passage', ['ref' => 'Matthew 4:2-3']))->assertOk()->assertJsonCount(2, 'data.sections.0.verses');
    $this->getJson(route('api.v1.bible.passage', ['ref' => 'Nothing here']))->assertNotFound();
    $this->getJson(route('api.v1.bible.passage'))->assertUnprocessable();
});

test('the member picks a translation and the reader uses it', function () {
    [$member] = studyMember();
    studyBible();

    $this->putJson(route('api.v1.bible.translation'), ['translation' => 'web'])->assertOk()->assertJson(['translation' => 'web']);
    expect($member->fresh()->bible_translation)->toBe('web');
    $this->getJson(route('api.v1.bible.read', ['MAT', 3]))->assertJsonPath('data.translation', 'web');
    $this->getJson(route('api.v1.bible.read', ['MAT', 3, 't' => 'kjv']))->assertJsonPath('data.translation', 'kjv');
    $this->putJson(route('api.v1.bible.translation'), ['translation' => 'klingon'])->assertUnprocessable();
});

test('when the Bible service is down the reader says so kindly', function () {
    studyMember();
    Http::fake(['bible-api.com/*' => Http::response('down', 503)]);

    $this->getJson(route('api.v1.bible.read', ['JHN', 3]))->assertStatus(503)->assertJsonPath('message', fn ($m) => str_contains($m, 'could not load'));
});

// ── Notes ───────────────────────────────────────────────────────────────────────────────────────────────────

test('notes are saved by what is being read, searched, pinned and deleted — and stay private', function () {
    [$member] = studyMember();

    $created = $this->postJson(route('api.v1.notes.store'), ['context' => 'bible:JHN.3', 'reference' => 'John 3', 'body' => 'God so loved the world.'])->assertCreated()->json('data');
    expect($created['kind'])->toBe('bible')->and($created['display_title'])->toBe('John 3');

    // Writing about the same chapter again continues the same note.
    $this->postJson(route('api.v1.notes.store'), ['context' => 'bible:JHN.3', 'body' => 'God so loved the world. Believe!'])->assertOk()->assertJsonPath('data.id', $created['id']);
    expect($this->getJson(route('api.v1.notes.context', ['context' => 'bible:JHN.3']))->json('data.body'))->toBe('God so loved the world. Believe!');
    expect($this->getJson(route('api.v1.notes.context', ['context' => 'bible:JHN.99']))->json('data'))->toBeNull();

    // A free-standing note, with a colour and a title.
    $free = $this->postJson(route('api.v1.notes.store'), ['title' => 'Prayer list', 'body' => "- Mum\n- Exams", 'color' => 'sky'])->assertCreated()->json('data');
    expect($free['kind'])->toBe('note')->and($free['color_hex'])->toBe('#EEF5FF');
    $this->patchJson(route('api.v1.notes.update', $free['id']), ['body' => "- Mum\n- Exams\n- Work"])->assertOk();
    $this->postJson(route('api.v1.notes.pin', $free['id']))->assertOk()->assertJsonPath('data.pinned', true);

    expect($this->getJson(route('api.v1.notes.index'))->json('data.*.id'))->toBe([$free['id'], $created['id']]); // pinned first
    expect($this->getJson(route('api.v1.notes.index', ['q' => 'Believe']))->json('data'))->toHaveCount(1);
    expect($this->getJson(route('api.v1.notes.index', ['kind' => 'bible']))->json('data'))->toHaveCount(1);

    // Someone else cannot see or change them.
    Sanctum::actingAs(member(), ['member']);
    $this->getJson(route('api.v1.notes.show', $created['id']))->assertNotFound();
    $this->patchJson(route('api.v1.notes.update', $created['id']), ['body' => 'x'])->assertNotFound();
    $this->deleteJson(route('api.v1.notes.destroy', $created['id']))->assertNotFound();

    Sanctum::actingAs($member, ['member']);
    $this->deleteJson(route('api.v1.notes.destroy', $created['id']))->assertOk();
    expect($this->getJson(route('api.v1.notes.index'))->json('data'))->toHaveCount(1);
});

// ── Reading plans and circles ─────────────────────────────────────────────────────────────────────────────

test('a member starts a reading plan, reads day by day and keeps a streak', function () {
    [, $church] = studyMember();
    $plan = studyPlan($church);
    $hidden = $church->readingPlans()->create(['title' => 'Draft', 'entries' => [['day' => 1, 'passages' => 'John 1']], 'days_count' => 1, 'is_published' => false]);

    $list = $this->getJson(route('api.v1.plans.index'))->assertOk()->json('data');
    expect($list['available'])->toHaveCount(1)->and($list['available'][0]['title'])->toBe('Matthew in 5 days')->and($list['mine'])->toBe([]);

    $this->postJson(route('api.v1.plans.start', $plan))->assertCreated()->assertJsonPath('data.progress.next_day', 1);
    $this->getJson(route('api.v1.plans.show', $hidden))->assertForbidden();

    $detail = $this->getJson(route('api.v1.plans.show', $plan))->assertOk()->json('data');
    expect($detail['day']['passages'])->toBe('Matthew 1')->and($detail['days'])->toHaveCount(5)->and($detail['progress']['percent'])->toEqual(0);

    $this->postJson(route('api.v1.plans.complete', $plan), ['day' => 1])->assertOk()->assertJsonPath('data.progress.streak', 1)->assertJsonPath('data.progress.percent', 20);
    expect($this->getJson(route('api.v1.plans.show', $plan))->json('data.day.passages'))->toBe('Matthew 2'); // opens on the next unread day
    expect($this->getJson(route('api.v1.plans.show', [$plan, 'day' => 1]))->json('data.day.done'))->toBeTrue();
    $this->postJson(route('api.v1.plans.complete', $plan), ['day' => 9])->assertUnprocessable();

    foreach ([2, 3, 4, 5] as $d) {
        $this->postJson(route('api.v1.plans.complete', $plan), ['day' => $d])->assertOk();
    }
    expect($this->getJson(route('api.v1.plans.index'))->json('data.mine.0.progress.completed_at'))->not->toBeNull();

    $this->deleteJson(route('api.v1.plans.leave', $plan))->assertOk();
    expect($this->getJson(route('api.v1.plans.index'))->json('data.mine'))->toBe([]);
});

test('reading partners join a circle with a code and share a private summary after each day', function () {
    [$owner, $church] = studyMember();
    $plan = studyPlan($church);
    $friend = member();
    $church->subscriptions()->create(['user_id' => $friend->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);
    $outsider = member();

    $circle = $this->postJson(route('api.v1.circles.store', $plan), ['name' => 'Morning readers', 'reading_time' => '06:00', 'reading_days' => [1, 3, 5]])
        ->assertCreated()->assertJsonPath('data.is_owner', true)->assertJsonPath('data.schedule', 'Mon, Wed, Fri · 6:00 AM')->json('data');
    expect($circle['code'])->toHaveLength(6)->and($circle['members'])->toHaveCount(1);

    // A partner joins by code; an outsider (not in the church) cannot; a wrong code is refused.
    Sanctum::actingAs($outsider, ['member']);
    $this->postJson(route('api.v1.circles.join'), ['code' => $circle['code']])->assertUnprocessable()->assertJsonValidationErrors('code');
    Sanctum::actingAs($friend, ['member']);
    $this->postJson(route('api.v1.circles.join'), ['code' => 'ZZZZZZ'])->assertUnprocessable();
    $this->postJson(route('api.v1.circles.join'), ['code' => strtolower($circle['code'])])->assertOk()->assertJsonCount(2, 'data.members')->assertJsonPath('data.is_owner', false);

    $this->postJson(route('api.v1.circles.complete', [$circle['id'], 1]), ['summary' => 'The baptism of Jesus.'])->assertOk();
    Sanctum::actingAs($owner, ['member']);
    $view = $this->getJson(route('api.v1.circles.show', $circle['id']))->assertOk()->json('data');
    $byName = collect($view['entries'])->keyBy('name');
    expect($view['current_day'])->toBe(1)->and($view['day']['passages'])->toBe('Matthew 1')
        ->and($byName[$friend->name]['done'])->toBeTrue()->and($byName[$friend->name]['summary'])->toBe('The baptism of Jesus.')
        ->and($byName['You']['done'])->toBeFalse();

    $this->postJson(route('api.v1.circles.complete', [$circle['id'], 1]), ['summary' => 'Prepare the way.'])->assertOk();
    expect($this->getJson(route('api.v1.circles.show', $circle['id']))->json('data.current_day'))->toBe(2);

    // Only the owner changes the schedule; a stranger cannot even look.
    $this->putJson(route('api.v1.circles.update', $circle['id']), ['name' => 'Early birds', 'reading_time' => '05:30', 'reading_days' => [0, 1, 2, 3, 4, 5, 6]])->assertOk()->assertJsonPath('data.schedule', 'Every day · 5:30 AM');
    Sanctum::actingAs($friend, ['member']);
    $this->putJson(route('api.v1.circles.update', $circle['id']), ['name' => 'Mine now', 'reading_time' => '07:00', 'reading_days' => [1]])->assertForbidden();
    Sanctum::actingAs($outsider, ['member']);
    $this->getJson(route('api.v1.circles.show', $circle['id']))->assertNotFound();

    Sanctum::actingAs($friend, ['member']);
    $this->deleteJson(route('api.v1.circles.leave', $circle['id']))->assertOk();
    expect($this->getJson(route('api.v1.plans.index'))->json('data.circles'))->toBe([]);
});
