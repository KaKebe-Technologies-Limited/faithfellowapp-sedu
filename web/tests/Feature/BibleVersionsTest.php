<?php

use App\Models\BibleChapter;
use App\Services\Bible\EsvApiProvider;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/*
| Choosing a Bible version: the ESV is the default once its key is set (until then the reader uses the King James
| Version), more public-domain versions are offered, and the licensed ESV is never stored on the server or the phone.
*/

function esvReader()
{
    $user = member();
    Sanctum::actingAs($user, ['member']);

    return $user;
}

test('without an ESV key the reader offers the free versions and defaults to the King James Version', function () {
    config(['services.bible.esv_key' => null]);
    esvReader();

    $data = $this->getJson(route('api.v1.bible.index'))->assertOk()->json('data');
    $keys = collect($data['translations'])->pluck('key');

    expect($data['translation'])->toBe('kjv')
        ->and($keys)->toContain('kjv', 'web', 'webbe', 'asv', 'bbe', 'darby', 'dra')->not->toContain('esv');
});

test('with an ESV key the ESV is the default version and the books are in canon order', function () {
    config(['services.bible.esv_key' => 'test-key']);
    esvReader();

    $data = $this->getJson(route('api.v1.bible.index'))->assertOk()->json('data');
    $books = collect($data['books']);

    expect($data['translation'])->toBe('esv')
        ->and(collect($data['translations'])->firstWhere('key', 'esv'))->toMatchArray(['short' => 'ESV', 'offline' => false])
        ->and($books)->toHaveCount(66)
        ->and($books->first()['code'])->toBe('GEN')->and($books->get(38)['code'])->toBe('MAL')->and($books->get(39)['code'])->toBe('MAT')->and($books->last()['code'])->toBe('REV');
});

test('an ESV chapter comes from the ESV API with its copyright, and is never stored', function () {
    config(['services.bible.esv_key' => 'test-key']);
    Http::fake(['api.esv.org/*' => Http::response(['passages' => ["[1] In the beginning, God created the heavens and the earth.\n\n[2] The earth was without form and void,\n   and darkness was over the face of the deep.\n"]])]);
    esvReader();

    $chapter = $this->getJson(route('api.v1.bible.read', ['GEN', 1]))->assertOk()->json('data');

    expect($chapter['translation'])->toBe('esv')->and($chapter['keep_offline'])->toBeFalse()->and($chapter['copyright'])->toBe(EsvApiProvider::COPYRIGHT)
        ->and($chapter['verses'])->toHaveCount(2)
        ->and($chapter['verses'][0]['t'])->toBe('In the beginning, God created the heavens and the earth.')
        ->and($chapter['verses'][1]['t'])->toBe('The earth was without form and void, and darkness was over the face of the deep.');
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'q=Genesis 1') && $request->hasHeader('Authorization', 'Token test-key'));
    expect(BibleChapter::where('translation', 'esv')->count())->toBe(0);
});

test('the ESV has nothing to download for offline reading', function () {
    config(['services.bible.esv_key' => 'test-key']);
    Http::fake();
    esvReader();

    $data = $this->getJson(route('api.v1.bible.download', ['RUT', 't' => 'esv']))->assertOk()->json('data');

    expect($data['chapters'])->toBe([])->and($data['missing'])->toBe([])->and($data['offline'])->toBeFalse();
    Http::assertNothingSent();
});

test('a member can choose another version, and gets that version from then on', function () {
    config(['services.bible.esv_key' => null]);
    Http::fake(['bible-api.com/*' => Http::response(['verses' => [['verse' => 1, 'text' => "The Lord is my shepherd.\n"]]])]);
    $user = esvReader();

    $this->putJson(route('api.v1.bible.translation'), ['translation' => 'darby'])->assertOk()->assertJson(['translation' => 'darby']);
    expect($user->fresh()->bible_translation)->toBe('darby');

    $this->getJson(route('api.v1.bible.read', ['PSA', 23]))->assertOk()->assertJsonPath('data.translation', 'darby')->assertJsonPath('data.keep_offline', true);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/darby/PSA/23'));

    // The ESV cannot be chosen while it has no key.
    $this->putJson(route('api.v1.bible.translation'), ['translation' => 'esv'])->assertUnprocessable();
});
