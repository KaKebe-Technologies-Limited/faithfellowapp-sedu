<?php

use App\Models\BlogPost;
use App\Models\Church;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

beforeEach(fn () => Cache::forget('sitemap.xml'));

test('sitemap lists public pages, active churches and published posts only', function () {
    Church::factory()->create(['slug' => 'listed-church']);
    Church::factory()->pending()->create(['slug' => 'pending-church']);
    BlogPost::create(['title' => 'Live', 'slug' => 'published-post', 'body' => 'x', 'status' => 'published', 'published_at' => now()->subDay()]);
    BlogPost::create(['title' => 'Draft', 'slug' => 'draft-post', 'body' => 'x', 'status' => 'draft']);

    $this->get('/sitemap.xml')->assertOk()->assertHeader('content-type', 'application/xml; charset=UTF-8')
        ->assertSee(route('churches.show', 'listed-church'), false)
        ->assertSee(route('blog.show', 'published-post'), false)
        ->assertDontSee('pending-church')->assertDontSee('draft-post');
});

test('robots.txt blocks everything outside production and points to the sitemap in production', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');

    app()->detectEnvironment(fn () => 'production');
    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.route('sitemap'))->assertSee('Disallow: /super-admin');
});

test('church pages carry schema.org structured data', function () {
    Church::factory()->create(['name' => 'Structured Church', 'slug' => 'structured', 'lat' => 2.25, 'lng' => 32.9]);

    $this->get(route('churches.show', 'structured'))->assertOk()
        ->assertSee('"@type":"Church"', false)->assertSee('"name":"Structured Church"', false)->assertSee('GeoCoordinates', false);
});

test('responses carry security headers and admin areas are not indexed', function () {
    $this->get('/')->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $this->actingAs(superAdmin())->get(route('super-admin.dashboard'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('visitors can switch language and see translated navigation', function () {
    $this->get(route('churches.index', ['lang' => 'sw']))->assertOk()->assertSee('Tafuta kanisa');
    $this->get(route('churches.index'))->assertSee('Tafuta kanisa'); // remembered in the session
    $this->get(route('churches.index', ['lang' => 'xx']))->assertOk(); // unsupported → ignored

    $member = member(['language' => 'lg']);
    $this->actingAs($member)->get(route('prayer.index'))->assertOk()->assertSee('Bbugwe ly&#039;okusaba', false);
});

test('every supported language has a translation file with valid JSON and placeholders intact', function () {
    $english = null;
    foreach (array_keys(config('faithfellows.languages')) as $locale) {
        if ($locale === 'en') {
            continue;
        }
        $path = lang_path($locale.'.json');
        expect(file_exists($path))->toBeTrue();
        $strings = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $english ??= array_keys($strings);
        expect(array_keys($strings))->toEqualCanonicalizing($english);

        foreach ($strings as $key => $value) {
            preg_match_all('/:\w+/', $key, $placeholders);
            foreach ($placeholders[0] as $placeholder) {
                expect($value)->toContain($placeholder);
            }
        }
    }
});

test('production password rules require letters and numbers', function () {
    app()->detectEnvironment(fn () => 'production');
    Password::defaults(fn () => Password::min(8)->letters()->numbers());

    expect(Validator::make(['p' => 'password'], ['p' => Password::defaults()])->fails())->toBeTrue()
        ->and(Validator::make(['p' => 'faith2026'], ['p' => Password::defaults()])->passes())->toBeTrue();
});
