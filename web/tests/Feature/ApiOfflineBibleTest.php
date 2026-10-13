<?php

use App\Models\BibleChapter;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/*
| Reading the Bible without a connection: the app downloads a book at a time (the server hands over what it holds and
| fetches a few more chapters from the Bible provider on each request), and the warm-up command fills the cache.
*/

function fakeProvider(): void
{
    Http::fake(['bible-api.com/*' => fn ($request) => Http::response(['verses' => collect(range(1, 3))->map(fn ($v) => [
        'verse' => $v, 'text' => 'Text '.$v." of {$request->url()}\n",
    ])->all()])]);
}

function offlineReader(): User
{
    $user = member();
    Sanctum::actingAs($user, ['member']);

    return $user;
}

test('the Bible index carries the book names people type, so the app can read references offline', function () {
    offlineReader();
    $books = collect($this->getJson(route('api.v1.bible.index'))->assertOk()->json('data.books'));

    expect($books)->toHaveCount(66)->and($books->firstWhere('code', 'JHN')['aliases'])->toContain('jn')->and($books->firstWhere('code', '1CO')['aliases'])->toContain('1 cor');
});

test('a book downloads in rounds: cached chapters at once, a few more fetched each time, none sent twice', function () {
    fakeProvider();
    offlineReader();
    // Jude has one chapter, Philemon one, 2 John one, 3 John one; Ruth has four. Pre-cache two of Ruth's.
    BibleChapter::create(['translation' => 'kjv', 'book' => 'RUT', 'chapter' => 1, 'verses' => [['v' => 1, 't' => 'Cached one.']]]);
    BibleChapter::create(['translation' => 'kjv', 'book' => 'RUT', 'chapter' => 3, 'verses' => [['v' => 1, 't' => 'Cached three.']]]);

    $first = $this->getJson(route('api.v1.bible.download', ['RUT', 't' => 'kjv']))->assertOk()->json('data');
    expect($first['total'])->toBe(4)->and($first['fetched'])->toBe(2)->and($first['missing'])->toBe([])
        ->and(array_keys($first['chapters']))->toEqual([1, 3, 2, 4])
        ->and($first['chapters'][1][0]['t'])->toBe('Cached one.')
        ->and($first['chapters'][2][0]['t'])->toContain('Text 1');
    expect(BibleChapter::where('book', 'RUT')->count())->toBe(4);

    // Asking again with what the app already stores returns nothing new.
    $again = $this->getJson(route('api.v1.bible.download', ['RUT', 't' => 'kjv', 'have' => '1,2,3,4']))->assertOk()->json('data');
    expect($again['chapters'])->toBe([])->and($again['missing'])->toBe([])->and($again['fetched'])->toBe(0);
});

test('a long book is fetched in steps of five chapters', function () {
    fakeProvider();
    offlineReader();

    $one = $this->getJson(route('api.v1.bible.download', ['PSA', 't' => 'web']))->assertOk()->json('data');
    expect($one['total'])->toBe(150)->and($one['fetched'])->toBe(5)->and($one['chapters'])->toHaveCount(5)->and($one['missing'])->toHaveCount(145);

    $two = $this->getJson(route('api.v1.bible.download', ['PSA', 't' => 'web', 'have' => '1,2,3,4,5']))->assertOk()->json('data');
    expect($two['fetched'])->toBe(5)->and(array_keys($two['chapters']))->toEqual([6, 7, 8, 9, 10])->and($two['missing'])->toHaveCount(140);
});

test('when the Bible provider is down the book stays missing and the app can try again later', function () {
    Http::fake(['bible-api.com/*' => Http::response('down', 503)]);
    offlineReader();

    $data = $this->getJson(route('api.v1.bible.download', ['JUD', 't' => 'kjv']))->assertOk()->json('data');
    expect($data['chapters'])->toBe([])->and($data['missing'])->toBe([1])->and($data['fetched'])->toBe(0);
});

test('unknown books and signed-out readers are turned away', function () {
    $this->getJson(route('api.v1.bible.download', ['JUD']))->assertUnauthorized();
    offlineReader();
    $this->getJson(route('api.v1.bible.download', ['XYZ']))->assertNotFound();
});

test('the warm-up command fills the cache for the books asked for and skips what it already has', function () {
    fakeProvider();
    BibleChapter::create(['translation' => 'kjv', 'book' => '2JN', 'chapter' => 1, 'verses' => [['v' => 1, 't' => 'Already here.']]]);

    $this->artisan('bible:warm', ['translation' => 'kjv', '--book' => ['JUD', '2JN', '3JN'], '--delay' => 0])->assertSuccessful();

    expect(BibleChapter::where('translation', 'kjv')->pluck('book')->sort()->values()->all())->toBe(['2JN', '3JN', 'JUD'])
        ->and(BibleChapter::where('book', '2JN')->first()->verses[0]['t'])->toBe('Already here.');
    Http::assertSentCount(2);

    $this->artisan('bible:warm', ['translation' => 'nonsense'])->assertFailed();
});
