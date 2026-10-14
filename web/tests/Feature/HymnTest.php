<?php

use App\Models\Hymn;
use App\Models\Hymnal;
use Database\Seeders\HymnSeeder;
use Laravel\Sanctum\Sanctum;

/*
| The hymn book: hymnals and hymns added only by the super admin (with credits), read, searched, favourited and
| downloaded for offline use by every member in the app.
*/

function aHymnal(array $overrides = []): Hymnal
{
    return Hymnal::create(array_merge(['name' => 'Classic Hymns', 'language' => 'en', 'source_name' => 'The Open Hymnal Project', 'source_url' => 'https://openhymnal.org/', 'is_published' => true], $overrides));
}

function aHymn(Hymnal $hymnal, array $overrides = []): Hymn
{
    return $hymnal->hymns()->create(array_merge([
        'number' => 1, 'title' => 'Amazing Grace', 'author' => 'John Newton (1725–1807)', 'composer' => 'James P. Carrell (1787–1854)', 'tune' => 'New Britain', 'year_written' => 1779,
        'copyright_status' => 'public_domain', 'themes' => ['Grace', 'Assurance'], 'status' => 'published',
        'verses' => [['number' => 1, 'lines' => ['Amazing grace! How sweet the sound', 'That saved a wretch like me!']], ['number' => 2, 'lines' => ["'Twas grace that taught my heart to fear,", 'And grace my fears relieved.']]],
        'refrain' => null,
    ], $overrides));
}

// ── The text of a hymn ──────────────────────────────────────────────────────────────────────────────────

test('typed words become verses and a refrain, numbers and spacing are tidied', function () {
    $text = "1. Blessed assurance,  Jesus is mine!\nO what a foretaste of glory divine!\n\nVerse 2) Perfect submission,\nperfect delight.\n\nRefrain:\nThis is my story,\nthis is my song.\n";
    $parsed = Hymn::parseLyrics($text);

    expect($parsed['verses'])->toHaveCount(2)->and($parsed['verses'][0])->toBe(['number' => 1, 'lines' => ['Blessed assurance, Jesus is mine!', 'O what a foretaste of glory divine!']])
        ->and($parsed['verses'][1]['lines'])->toBe(['Perfect submission,', 'perfect delight.'])
        ->and($parsed['refrain'])->toBe(['lines' => ['This is my story,', 'this is my song.']]);
    expect(Hymn::parseLyrics("Chorus: Sing again\n\nOne verse only")['refrain'])->toBe(['lines' => ['Sing again']]);
});

test('a hymn is shown in singing order with the refrain after every verse, and credits that name everyone', function () {
    $hymn = aHymn(aHymnal(), ['verses' => [['number' => 1, 'lines' => ['A']], ['number' => 2, 'lines' => ['B']]], 'refrain' => ['lines' => ['R']], 'translator' => 'John Young', 'arranger' => 'E. Excell']);

    expect(collect($hymn->sections())->map(fn ($s) => $s['type'].':'.implode('|', $s['lines']))->all())->toBe(['verse:A', 'refrain:R', 'verse:B', 'refrain:R']);
    $credits = collect($hymn->creditLines())->pluck('value', 'label');
    expect($credits['Words'])->toBe('John Newton (1725–1807), 1779')->and($credits['Music'])->toContain('New Britain')->toContain('James P. Carrell')
        ->and($credits['Translated by'])->toBe('John Young')->and($credits['Copyright'])->toBe('Public domain')->and($credits['Text collected from'])->toContain('The Open Hymnal Project');
    expect($hymn->shareText())->toContain('1. Amazing Grace')->toContain('Words: John Newton')->toContain('FaithFellows hymn book');
});

test('saving a hymn fills in its first line and the words used for searching', function () {
    $hymn = aHymn(aHymnal());

    expect($hymn->first_line)->toBe('Amazing grace! How sweet the sound')->and($hymn->search_text)->toContain('wretch like me')->toContain('john newton');
    expect($hymn->hymnal->fresh()->hymns_count)->toBe(1);
});

test('the first hymnal is public domain, credited, and loads only once', function () {
    $this->seed(HymnSeeder::class);
    $this->seed(HymnSeeder::class);

    $hymnal = Hymnal::where('slug', 'classic-hymns')->sole();
    expect($hymnal->hymns()->count())->toBeGreaterThan(150)->and($hymnal->hymns_count)->toBe($hymnal->hymns()->count())->and($hymnal->source_name)->toBe('The Open Hymnal Project');

    $numbers = $hymnal->hymns()->pluck('number');
    expect($numbers->unique()->count())->toBe($numbers->count());
    foreach ($hymnal->hymns()->get() as $hymn) {
        expect($hymn->copyright_status)->toBe('public_domain')->and($hymn->author)->not->toBeEmpty()->and($hymn->source_url)->toStartWith('https://github.com/mzealey/openhymnal/')
            ->and($hymn->verses)->not->toBeEmpty()->and(collect($hymn->verses)->pluck('lines')->flatten()->every(fn ($l) => trim($l) !== '' && ! str_contains($l, '*') && ! str_contains($l, '~')))->toBeTrue();
        // Nobody who is alive today is in this collection.
        expect($hymn->author.$hymn->composer.$hymn->arranger)->not->toMatch('/\(\d{4}\s*[–-]\s*\)/u');
    }
    expect($hymnal->hymns()->where('title', 'Amazing Grace')->exists())->toBeTrue();
});

// ── In the app ──────────────────────────────────────────────────────────────────────────────────────────

test('hymns need a signed-in member', function () {
    $this->getJson(route('api.v1.hymns.hymnals'))->assertUnauthorized();
});

test('members see the published hymnals and how many favourites they have', function () {
    $member = member();
    Sanctum::actingAs($member, ['member']);
    $classic = aHymnal();
    aHymn($classic);
    aHymnal(['name' => 'Hidden hymnal', 'is_published' => false]);
    aHymnal(['name' => 'Luganda hymnal', 'language' => 'lg', 'sort' => 5]);

    $data = $this->getJson(route('api.v1.hymns.hymnals'))->assertOk()->json('data');
    expect(collect($data['hymnals'])->pluck('name')->all())->toBe(['Classic Hymns', 'Luganda hymnal'])->and($data['hymnals'][0])->toMatchArray(['hymns_count' => 1, 'language_label' => 'English', 'source_name' => 'The Open Hymnal Project'])
        ->and(collect($data['languages'])->pluck('label')->all())->toBe(['English', 'Luganda'])->and($data['favorites'])->toBe(0);
});

test('a hymnal lists its hymns in number order, and can be filtered by words or theme', function () {
    Sanctum::actingAs(member(), ['member']);
    $hymnal = aHymnal();
    aHymn($hymnal, ['number' => 2, 'title' => 'Blessed Assurance', 'author' => 'Fanny Crosby', 'themes' => ['Assurance'], 'verses' => [['number' => 1, 'lines' => ['Blessed assurance, Jesus is mine!']]]]);
    aHymn($hymnal);
    aHymn($hymnal, ['number' => 3, 'title' => 'Draft one', 'status' => 'draft']);

    $all = $this->getJson(route('api.v1.hymns.hymnal', $hymnal))->assertOk()->json('data');
    expect(collect($all['hymns'])->pluck('title')->all())->toBe(['Amazing Grace', 'Blessed Assurance'])->and($all['hymns'][0])->not->toHaveKey('sections')->and($all['themes'])->not->toBeEmpty();

    expect(collect($this->getJson(route('api.v1.hymns.hymnal', [$hymnal, 'q' => 'jesus is mine']))->json('data.hymns'))->pluck('title')->all())->toBe(['Blessed Assurance']);
    expect(collect($this->getJson(route('api.v1.hymns.hymnal', [$hymnal, 'theme' => 'Grace']))->json('data.hymns'))->pluck('title')->all())->toBe(['Amazing Grace']);
    expect(collect($this->getJson(route('api.v1.hymns.hymnal', [$hymnal, 'q' => '2']))->json('data.hymns'))->pluck('number')->all())->toBe([2]);
    $this->getJson(route('api.v1.hymns.hymnal', aHymnal(['name' => 'Off', 'is_published' => false])))->assertNotFound();
});

test('a hymn comes with its sections, credits, neighbours and the member\'s favourite mark', function () {
    $member = member();
    Sanctum::actingAs($member, ['member']);
    $hymnal = aHymnal();
    $first = aHymn($hymnal, ['number' => 1]);
    $second = aHymn($hymnal, ['number' => 2, 'title' => 'Second', 'refrain' => ['lines' => ['Sing on']]]);
    $third = aHymn($hymnal, ['number' => 3, 'title' => 'Third']);
    $member->favoriteHymns()->attach($second->id);

    $data = $this->getJson(route('api.v1.hymns.show', $second))->assertOk()->json('data');
    expect($data)->toMatchArray(['id' => $second->id, 'number' => 2, 'title' => 'Second', 'favorite' => true, 'prev_id' => $first->id, 'next_id' => $third->id, 'copyright_label' => 'Public domain'])
        ->and(collect($data['sections'])->pluck('type')->all())->toBe(['verse', 'refrain', 'verse', 'refrain'])
        ->and(collect($data['credits'])->pluck('label')->all())->toContain('Words', 'Music', 'Copyright', 'Text collected from')
        ->and($data['share_text'])->toContain('Words: John Newton');

    $draft = aHymn($hymnal, ['number' => 4, 'title' => 'Draft', 'status' => 'draft']);
    $this->getJson(route('api.v1.hymns.show', $draft))->assertNotFound();
});

test('hymns are found by number, title, first line or any words', function () {
    Sanctum::actingAs(member(), ['member']);
    $hymnal = aHymnal();
    aHymn($hymnal, ['number' => 23, 'title' => 'The King of Love', 'author' => 'Henry Baker', 'verses' => [['number' => 1, 'lines' => ['The King of love my Shepherd is,', 'Whose goodness faileth never']]]]);
    aHymn($hymnal, ['number' => 7]);

    $titles = fn (array $params) => collect($this->getJson(route('api.v1.hymns.search', $params))->assertOk()->json('data'))->pluck('title')->all();
    expect($titles(['q' => '23']))->toBe(['The King of Love'])
        ->and($titles(['q' => 'amazing']))->toBe(['Amazing Grace'])
        ->and($titles(['q' => 'goodness faileth']))->toBe(['The King of Love'])
        ->and($titles(['q' => 'baker']))->toBe(['The King of Love'])
        ->and($titles(['q' => 'wretch']))->toBe(['Amazing Grace'])
        ->and($titles(['q' => 'nothinglikethis']))->toBe([])
        ->and($titles(['q' => 'grace', 'language' => 'lg']))->toBe([]);
    $this->getJson(route('api.v1.hymns.search'))->assertUnprocessable();
});

test('favourites can be added, listed and removed', function () {
    $member = member();
    Sanctum::actingAs($member, ['member']);
    $hymn = aHymn(aHymnal());

    $this->postJson(route('api.v1.hymns.favorite', $hymn))->assertOk()->assertJson(['favorite' => true, 'favorites_count' => 1]);
    $this->postJson(route('api.v1.hymns.favorite', $hymn))->assertOk()->assertJson(['favorites_count' => 1]);
    expect(collect($this->getJson(route('api.v1.hymns.favorites'))->json('data'))->pluck('id')->all())->toBe([$hymn->id])
        ->and($this->getJson(route('api.v1.hymns.hymnals'))->json('data.favorites'))->toBe(1);

    $this->deleteJson(route('api.v1.hymns.unfavorite', $hymn))->assertOk()->assertJson(['favorite' => false, 'favorites_count' => 0]);
    expect($this->getJson(route('api.v1.hymns.favorites'))->json('data'))->toBe([]);
});

test('a whole hymnal downloads in one go for offline use', function () {
    Sanctum::actingAs(member(), ['member']);
    $hymnal = aHymnal();
    aHymn($hymnal, ['number' => 1]);
    aHymn($hymnal, ['number' => 2, 'title' => 'Two']);
    aHymn($hymnal, ['number' => 3, 'title' => 'Hidden', 'status' => 'draft']);

    $data = $this->getJson(route('api.v1.hymns.download', $hymnal))->assertOk()->json('data');
    expect($data['hymns'])->toHaveCount(2)->and($data['hymns'][0])->toHaveKeys(['sections', 'credits', 'share_text', 'first_line'])->and($data['downloaded_at'])->not->toBeEmpty()
        ->and($data['source_name'])->toBe('The Open Hymnal Project');
});

// ── Only the super admin adds hymns ─────────────────────────────────────────────────────────────────────

test('members and church admins cannot reach the hymn book on the website', function () {
    $hymnal = aHymnal();
    $this->actingAs(member())->get(route('super-admin.hymnals.index'))->assertForbidden();
    $this->actingAs(member())->post(route('super-admin.hymns.store', $hymnal), ['title' => 'Sneaky'])->assertForbidden();
    $this->actingAs(member())->delete(route('super-admin.hymnals.destroy', $hymnal))->assertForbidden();
    expect(Hymnal::count())->toBe(1);
});

test('the super admin adds a hymnal and a hymn with its credits, edits it and deletes it', function () {
    $admin = superAdmin();
    $this->actingAs($admin)->get(route('super-admin.hymnals.index'))->assertOk()->assertSee('Hymn book');

    $this->actingAs($admin)->post(route('super-admin.hymnals.store'), ['name' => 'Nyimbo za Kristo', 'language' => 'sw', 'denomination' => 'SDA', 'source_name' => 'SDA Church', 'is_published' => 1])->assertRedirect();
    $hymnal = Hymnal::where('language', 'sw')->sole();
    expect($hymnal->slug)->toBe('nyimbo-za-kristo')->and($hymnal->is_published)->toBeTrue();

    $this->actingAs($admin)->get(route('super-admin.hymns.create', $hymnal))->assertOk()->assertSee('Words of the hymn');
    $payload = ['number' => 5, 'title' => 'Yesu Ananipenda', 'lyrics' => "1. Yesu ananipenda,\nanipenda sana.\n\nRefrain:\nNa nampenda Yesu.", 'author' => 'Traditional', 'composer' => 'Unknown', 'copyright_status' => 'public_domain', 'themes' => 'love, Jesus, love', 'status' => 'published'];
    $this->actingAs($admin)->post(route('super-admin.hymns.store', $hymnal), $payload)->assertRedirect(route('super-admin.hymnals.show', $hymnal));

    $hymn = Hymn::sole();
    expect($hymn->title)->toBe('Yesu Ananipenda')->and($hymn->verses[0]['lines'])->toBe(['Yesu ananipenda,', 'anipenda sana.'])->and($hymn->refrain['lines'])->toBe(['Na nampenda Yesu.'])
        ->and($hymn->themes)->toBe(['love', 'Jesus'])->and($hymn->first_line)->toBe('Yesu ananipenda')->and($hymnal->fresh()->hymns_count)->toBe(1);
    $this->actingAs($admin)->get(route('super-admin.hymnals.show', $hymnal))->assertOk()->assertSee('Yesu Ananipenda')->assertSee('Traditional');

    $this->actingAs($admin)->get(route('super-admin.hymns.edit', [$hymnal, $hymn]))->assertOk()->assertSee('Refrain:', false);
    $this->actingAs($admin)->put(route('super-admin.hymns.update', [$hymnal, $hymn]), ['lyrics' => "Verse one changed\n\nVerse two"] + $payload)->assertRedirect();
    expect($hymn->fresh()->verses)->toHaveCount(2)->and($hymn->fresh()->refrain)->toBeNull();

    $this->actingAs($admin)->delete(route('super-admin.hymns.destroy', [$hymnal, $hymn]))->assertRedirect();
    expect(Hymn::count())->toBe(0)->and($hymnal->fresh()->hymns_count)->toBe(0);
    $this->actingAs($admin)->delete(route('super-admin.hymnals.destroy', $hymnal))->assertRedirect(route('super-admin.hymnals.index'));
    expect(Hymnal::count())->toBe(0);
});

test('a hymn cannot be saved without credits, without words, or with a number already used', function () {
    $admin = superAdmin();
    $hymnal = aHymnal();
    aHymn($hymnal, ['number' => 5]);
    $base = ['number' => 6, 'title' => 'Test', 'lyrics' => "A line\nAnother line", 'author' => 'Someone', 'copyright_status' => 'public_domain', 'status' => 'published'];
    $post = fn (array $data) => $this->actingAs($admin)->post(route('super-admin.hymns.store', $hymnal), $data + $base);

    $post(['author' => ''])->assertSessionHasErrors('author');
    $post(['lyrics' => ''])->assertSessionHasErrors('lyrics');
    $post(['lyrics' => "Refrain:\nOnly a refrain"])->assertSessionHasErrors('lyrics');
    $post(['number' => 5])->assertSessionHasErrors('number');
    $post(['copyright_status' => 'stolen'])->assertSessionHasErrors('copyright_status');
    $post(['audio_url' => 'http://insecure.example.com/a.mp3'])->assertSessionHasErrors('audio_url');
    expect($hymnal->hymns()->count())->toBe(1);
});
