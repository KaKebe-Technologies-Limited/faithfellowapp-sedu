<?php

use App\Models\Hymn;
use App\Models\Hymnal;
use App\Services\HymnImporter;
use Laravel\Sanctum\Sanctum;

/*
| Printed hymn books (like a Catholic hymn book with entrance, offertory and communion hymns): the parts of a book, hymns
| kept as they are printed, credits that name the book, and loading a whole hymnal from a file.
*/

function bookWithParts(array $overrides = []): Hymnal
{
    return Hymnal::create(array_merge([
        'name' => 'Mujje Tutendereze Omukama', 'language' => 'lg', 'denomination' => 'Catholic', 'source_name' => 'Inter-Diocesan Committee (Marianum Press, 2003)', 'is_published' => true,
        'sections' => [['name' => 'Ez’Amayingira', 'meaning' => 'Entrance hymns'], ['name' => 'Ez’Ekitambiro', 'meaning' => 'Offertory hymns'], ['name' => 'Ezitaliiko', 'meaning' => null]],
    ], $overrides));
}

function bookHymn(Hymnal $hymnal, string $section, int $number, array $overrides = []): Hymn
{
    $at = collect($hymnal->sections)->pluck('name')->search($section);

    return $hymnal->hymns()->create(array_merge([
        'section' => $section, 'section_order' => $at === false ? 0 : $at + 1, 'number' => $number, 'title' => "Hymn {$number}", 'author' => 'Fr. John Mary', 'composer' => 'Fr. John Mary',
        'copyright_status' => 'hymnal', 'status' => 'published', 'verses' => [['number' => 1, 'lines' => ["Line of hymn {$number}", 'Second line']]], 'refrain' => null,
    ], $overrides));
}

test('the parts of a book are written one per line with their meaning, and only parts that have hymns are listed', function () {
    expect(Hymnal::parseSections("Ez’Amayingira | Entrance hymns\n\n  Ez’Ekitambiro  \nEz’Amayingira | again"))->toBe([['name' => 'Ez’Amayingira', 'meaning' => 'Entrance hymns'], ['name' => 'Ez’Ekitambiro', 'meaning' => null]])
        ->and(Hymnal::parseSections(null))->toBeNull();

    $book = bookWithParts();
    bookHymn($book, 'Ez’Ekitambiro', 20);
    bookHymn($book, 'Ez’Amayingira', 1);
    bookHymn($book, 'Ez’Amayingira', 2, ['status' => 'draft']);

    expect($book->fresh()->sectionList())->toBe([['name' => 'Ez’Amayingira', 'meaning' => 'Entrance hymns', 'count' => 1], ['name' => 'Ez’Ekitambiro', 'meaning' => 'Offertory hymns', 'count' => 1]]);
});

test('hymns of a book with parts are listed part by part and previous and next walk through the parts', function () {
    Sanctum::actingAs(member(), ['member']);
    $book = bookWithParts();
    $offertory = bookHymn($book, 'Ez’Ekitambiro', 10);
    $entranceB = bookHymn($book, 'Ez’Amayingira', 5);
    $entranceA = bookHymn($book, 'Ez’Amayingira', 1);

    $data = $this->getJson(route('api.v1.hymns.hymnal', $book))->assertOk()->json('data');
    expect(collect($data['hymns'])->pluck('number')->all())->toBe([1, 5, 10])->and($data['hymns'][0]['section'])->toBe('Ez’Amayingira')
        ->and(collect($data['sections'])->pluck('name')->all())->toBe(['Ez’Amayingira', 'Ez’Ekitambiro'])->and($data['sections'][0])->toMatchArray(['meaning' => 'Entrance hymns', 'count' => 2]);

    $middle = $this->getJson(route('api.v1.hymns.show', $entranceB))->assertOk()->json('data');
    expect($middle)->toMatchArray(['section' => 'Ez’Amayingira', 'section_meaning' => 'Entrance hymns', 'prev_id' => $entranceA->id, 'next_id' => $offertory->id]);
    expect($this->getJson(route('api.v1.hymns.show', $offertory))->json('data.prev_id'))->toBe($entranceB->id);

    $download = $this->getJson(route('api.v1.hymns.download', $book))->assertOk()->json('data');
    expect($download['sections'])->toHaveCount(2)->and(collect($download['hymns'])->pluck('number')->all())->toBe([1, 5, 10])->and($download['hymns'][0]['section_meaning'])->toBe('Entrance hymns');

    // The same number can be used again in another part of the book, and a search finds the part's name too.
    $again = bookHymn($book, 'Ez’Ekitambiro', 1, ['title' => 'Again one']);
    expect(collect($this->getJson(route('api.v1.hymns.search', ['q' => 'ekitambiro']))->json('data'))->pluck('id')->all())->toContain($again->id, $offertory->id);
});

test('printed text blocks are kept as they are, without verse numbers or a refrain after them', function () {
    $book = bookWithParts();
    $hymn = bookHymn($book, 'Ez’Amayingira', 3, [
        'verses' => [['type' => 'text', 'label' => '', 'lines' => ['Mujje tutendereze', 'Omukama waffe']], ['number' => 1, 'lines' => ['Ekibiina kye']], ['type' => 'text', 'label' => 'Coda', 'lines' => ['Amiina']]],
        'refrain' => ['lines' => ['Aleluya']],
    ]);

    expect(collect($hymn->sections())->map(fn ($s) => $s['type'].':'.$s['label'])->all())->toBe(['text:', 'verse:1', 'refrain:Refrain', 'text:Coda']);
    expect($hymn->shareText())->toContain('Coda')->toContain('Ez’Amayingira');

    $parsed = Hymn::parseLyrics("Intro:\nA short intro\n\nFirst verse\n\nSecond verse\n\nText:\nAs printed\n\nRefrain:\nSing\n\nCoda:\nAmen");
    expect(collect($parsed['verses'])->map(fn ($v) => ($v['type'] ?? 'verse').':'.($v['number'] ?? $v['label']))->all())->toBe(['text:Intro', 'verse:1', 'verse:2', 'text:', 'text:Coda'])->and($parsed['refrain'])->toBe(['lines' => ['Sing']]);

    // Editing a hymn in the admin and saving it again keeps the blocks as they were.
    $edited = Hymn::parseLyrics($hymn->lyricsForEditing());
    expect($edited['verses'])->toBe($hymn->verses)->and($edited['refrain'])->toBe($hymn->refrain);

    $onlyBlocks = bookHymn($book, 'Ez’Amayingira', 4, ['verses' => [['type' => 'text', 'label' => '', 'lines' => ['Just words']]], 'refrain' => ['lines' => ['Chorus line']]]);
    expect(collect($onlyBlocks->sections())->pluck('type')->all())->toBe(['refrain', 'text']);
});

test('a hymn from a printed book credits the book, and says so when the book does not name the composer', function () {
    $book = bookWithParts();
    $named = bookHymn($book, 'Ez’Amayingira', 1, ['credit_note' => 'From the hymn book, page 12.']);
    $unnamed = bookHymn($book, 'Ez’Amayingira', 2, ['author' => null, 'composer' => null]);
    $different = bookHymn($book, 'Ez’Amayingira', 3, ['author' => 'Fr. A', 'composer' => 'Mr. B']);

    $credits = fn (Hymn $h) => collect($h->creditLines())->pluck('value', 'label');
    expect($credits($named)['Words and music'])->toBe('Fr. John Mary')->and($credits($named)['Copyright'])->toBe('From the printed hymn book')->and($credits($named)['Part of the book'])->toBe('Ez’Amayingira')
        ->and($credits($named)['Text collected from'])->toContain('Marianum Press')
        ->and($credits($unnamed)['Words and music'])->toBe('Not named in the book')
        ->and($credits($different)['Words'])->toBe('Fr. A')->and($credits($different)['Music'])->toContain('Mr. B');
});

// ── Loading a whole hymnal from a file ──────────────────────────────────────────────────────────────────

function hymnalFile(): array
{
    return [
        'hymnal' => ['name' => 'Gwe Katonda', 'slug' => 'gwe-katonda', 'language' => 'laj', 'denomination' => 'Catholic', 'description' => 'A small test book.', 'source_name' => 'Test Press',
            'sections' => [['name' => 'Entrance', 'meaning' => 'Entrance hymns'], ['name' => 'Communion', 'meaning' => 'Communion hymns']]],
        'hymns' => [
            ['section' => 'Communion', 'number' => 2, 'title' => 'Two', 'author' => 'Fr. Two', 'composer' => 'Fr. Two', 'copyright_status' => 'hymnal', 'verses' => [['number' => 1, 'lines' => ['Line one', ' ', 'Line two']]], 'refrain' => ['lines' => ['Refrain line']]],
            ['section' => 'Entrance', 'number' => 1, 'title' => 'One', 'author' => 'Fr. One', 'verses' => [['type' => 'text', 'label' => '', 'lines' => ['As printed']]]],
            ['section' => 'Entrance', 'number' => 9, 'title' => '', 'verses' => [['number' => 1, 'lines' => ['No title']]]],
            ['section' => 'Entrance', 'number' => 10, 'title' => 'No words', 'verses' => [['number' => 1, 'lines' => [' ']]]],
        ],
    ];
}

test('a hymnal file is loaded with its parts and credits, and loading it again updates instead of duplicating', function () {
    $importer = app(HymnImporter::class);
    $result = $importer->import(hymnalFile());

    expect($result)->toMatchArray(['created' => 2, 'updated' => 0, 'skipped' => 2]);
    $book = $result['hymnal'];
    expect($book->slug)->toBe('gwe-katonda')->and($book->language)->toBe('laj')->and($book->is_published)->toBeTrue()->and($book->hymns_count)->toBe(2)->and($book->sections)->toHaveCount(2);

    $two = $book->hymns()->where('number', 2)->sole();
    expect($two->section)->toBe('Communion')->and($two->section_order)->toBe(2)->and($two->verses[0]['lines'])->toBe(['Line one', 'Line two'])->and($two->copyright_status)->toBe('hymnal')
        ->and($two->search_text)->toContain('line one')->toContain('communion');
    $one = $book->hymns()->where('number', 1)->sole();
    expect($one->section_order)->toBe(1)->and($one->copyright_status)->toBe('hymnal');

    // A corrected file changes the same hymns and adds the new ones.
    $fixed = hymnalFile();
    $fixed['hymns'][0]['title'] = 'Two, corrected';
    $fixed['hymns'][] = ['section' => 'Communion', 'number' => 3, 'title' => 'Three', 'author' => 'Fr. Three', 'verses' => [['number' => 1, 'lines' => ['Third']]]];
    $again = $importer->import($fixed);
    expect($again)->toMatchArray(['created' => 1, 'updated' => 2, 'skipped' => 2])->and($again['hymnal']->hymns_count)->toBe(3)->and(Hymnal::count())->toBe(1)->and(Hymn::count())->toBe(3)
        ->and(Hymn::where('number', 2)->sole()->title)->toBe('Two, corrected');
});

test('a hymnal file can be loaded hidden, and a bad file is refused with a clear reason', function () {
    $importer = app(HymnImporter::class);
    expect($importer->import(hymnalFile(), publish: false)['hymnal']->is_published)->toBeFalse();

    expect(fn () => $importer->import(['hymns' => []]))->toThrow(InvalidArgumentException::class, 'needs a "hymnal" with a name');
    $bad = hymnalFile();
    $bad['hymnal']['language'] = 'xx';
    expect(fn () => $importer->import($bad))->toThrow(InvalidArgumentException::class, 'Unknown language');
});

test('hymns:import loads a file from disk', function () {
    $path = tempnam(sys_get_temp_dir(), 'hymnal');
    file_put_contents($path, json_encode(hymnalFile(), JSON_UNESCAPED_UNICODE));

    $this->artisan('hymns:import', ['file' => $path])->expectsOutputToContain('2 added')->expectsOutputToContain('showing in the app')->assertSuccessful();
    expect(Hymnal::where('slug', 'gwe-katonda')->sole()->hymns()->count())->toBe(2);

    $this->artisan('hymns:import', ['file' => $path.'-missing'])->expectsOutputToContain('File not found')->assertFailed();
    file_put_contents($path, '{ not json');
    $this->artisan('hymns:import', ['file' => $path])->assertFailed();
    unlink($path);
});

// ── The super admin and the parts of a book ─────────────────────────────────────────────────────────────

test('the super admin sets the parts of a book and puts each hymn in a part, numbers repeat across parts but not inside one', function () {
    $admin = superAdmin();
    $this->actingAs($admin)->post(route('super-admin.hymnals.store'), ['name' => 'Ennyimba', 'language' => 'lg', 'sections_text' => "Entrance | Entrance hymns\nCommunion", 'is_published' => 1])->assertRedirect();
    $book = Hymnal::where('slug', 'ennyimba')->sole();
    expect($book->sections)->toBe([['name' => 'Entrance', 'meaning' => 'Entrance hymns'], ['name' => 'Communion', 'meaning' => null]]);

    $this->actingAs($admin)->get(route('super-admin.hymns.create', $book))->assertOk()->assertSee('Part of the book');
    $payload = ['section' => 'Communion', 'number' => 1, 'title' => 'First', 'lyrics' => "A line\nAnother", 'author' => 'Fr. X', 'copyright_status' => 'public_domain', 'status' => 'published'];
    $this->actingAs($admin)->post(route('super-admin.hymns.store', $book), $payload)->assertRedirect();
    expect(Hymn::sole())->toMatchArray(['section' => 'Communion', 'section_order' => 2]);

    $this->actingAs($admin)->post(route('super-admin.hymns.store', $book), ['section' => 'Entrance', 'title' => 'Same number elsewhere'] + $payload)->assertRedirect();
    expect(Hymn::count())->toBe(2)->and(Hymn::where('title', 'Same number elsewhere')->sole()->section_order)->toBe(1);
    $this->actingAs($admin)->post(route('super-admin.hymns.store', $book), ['title' => 'Duplicate'] + $payload)->assertSessionHasErrors('number');

    // A hymn from a printed book may have no named author; any other hymn must be credited.
    $this->actingAs($admin)->post(route('super-admin.hymns.store', $book), ['number' => 7, 'title' => 'Anonymous', 'author' => '', 'copyright_status' => 'hymnal'] + $payload)->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('super-admin.hymns.store', $book), ['number' => 8, 'title' => 'Anonymous too', 'author' => ''] + $payload)->assertSessionHasErrors('author');
});

test('the update that shows the Luganda hymn book publishes it where it was loaded hidden, and leaves other books alone', function () {
    $luganda = Hymnal::create(['name' => 'Mujje Tutendereze Omukama', 'slug' => 'mujje-tutendereze-omukama', 'language' => 'lg', 'is_published' => false]);
    $other = Hymnal::create(['name' => 'Another book', 'slug' => 'another-book', 'language' => 'en', 'is_published' => false]);

    (require base_path('database/migrations/2026_01_25_000001_publish_luganda_hymnal.php'))->up();

    expect($luganda->fresh()->is_published)->toBeTrue()->and($other->fresh()->is_published)->toBeFalse();
});
