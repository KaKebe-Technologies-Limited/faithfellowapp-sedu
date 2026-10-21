<?php

use App\Models\Church;
use App\Models\Sermon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Uploaded pictures are served from /storage. When the server's storage link is missing the app answers /storage/… itself, and a picture whose file is
| gone shows the FaithFellows stand-in instead of a broken image.
*/

beforeEach(function () {
    Storage::fake('public');
});

test('a file in the public storage is served from /storage by the app when the web server did not', function () {
    Storage::disk('public')->put('9/conversions/photo-thumb.jpg', 'not really a picture');

    $this->get('/storage/9/conversions/photo-thumb.jpg')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    expect($this->get('/storage/9/conversions/photo-thumb.jpg')->headers->get('Cache-Control'))->toContain('max-age=86400');
});

test('only pictures and media files are served, and never outside the public storage', function () {
    Storage::disk('public')->put('9/notes.txt', 'private notes');
    Storage::disk('public')->put('9/script.php', '<?php echo 1;');

    $this->get('/storage/9/notes.txt')->assertNotFound();
    $this->get('/storage/9/script.php')->assertNotFound();
    $this->get('/storage/..%2F..%2F.env')->assertNotFound();
    $this->get('/storage/9/%2e%2e/%2e%2e/composer.json')->assertNotFound();
    $this->get('/storage/9/missing.jpg')->assertNotFound();
});

test('a church whose logo or banner file is gone gets the FaithFellows stand-in on its brand colour', function () {
    $church = Church::factory()->create(['brand_color' => '#1E7F74']);
    $logo = $church->addMedia(UploadedFile::fake()->image('logo.png', 400, 400))->toMediaCollection('logo');
    $cover = $church->addMedia(UploadedFile::fake()->image('cover.jpg', 1600, 600))->toMediaCollection('cover');
    Storage::disk('public')->deleteDirectory((string) $logo->id);
    Storage::disk('public')->deleteDirectory((string) $cover->id);

    $picture = $this->get("/storage/{$logo->id}/conversions/logo-thumb.jpg")->assertOk();
    $file = $picture->baseResponse->getFile()->getPathname();
    expect(getimagesize($file)[0])->toBe(512)->and(getimagesize($file)['mime'])->toBe('image/png');

    $banner = $this->get("/storage/{$cover->id}/conversions/cover-banner.jpg")->assertOk();
    expect(getimagesize($banner->baseResponse->getFile()->getPathname())[0])->toBe(1600);
});

test('a sermon whose picture file is gone shows its generated cover, and a missing profile photo is simply not found', function () {
    $church = Church::factory()->create();
    $sermon = $church->sermons()->create(['title' => 'Grace', 'language' => 'en', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()]);
    $thumb = $sermon->addMedia(UploadedFile::fake()->image('thumb.jpg', 800, 450))->toMediaCollection('thumbnail');
    Storage::disk('public')->deleteDirectory((string) $thumb->id);

    $this->get("/storage/{$thumb->id}/conversions/thumb-card.jpg")->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    $user = member();
    $photo = $user->addMedia(UploadedFile::fake()->image('me.jpg', 300, 300))->toMediaCollection('avatar');
    Storage::disk('public')->deleteDirectory((string) $photo->id);
    $this->get("/storage/{$photo->id}/conversions/me-thumb.jpg")->assertNotFound();
    expect(Sermon::count())->toBe(1);
});

test('the command that makes the storage link says what it found', function () {
    $this->artisan('ff:storage-link')->assertExitCode(0);
});
