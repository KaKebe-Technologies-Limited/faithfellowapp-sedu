<?php

use App\Models\AppPhoto;
use App\Models\AuditLog;
use App\Models\Church;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| The pictures of the app itself are the platform admin's to set: the opening photos, the welcome screen's photos and the default photos of the Home
| banner slider. The app asks for them before anyone has signed in; an empty list means "use the photos built into the app".
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

function appPhoto(string $name = 'photo.jpg', int $w = 900, int $h = 1400): UploadedFile
{
    return UploadedFile::fake()->image($name, $w, $h);
}

test('until the admin adds pictures the app is told to use its own', function () {
    $this->getJson(route('api.v1.app.media'))->assertOk()->assertJson(['data' => ['opening' => [], 'welcome' => [], 'banner' => []]])
        ->assertHeader('Cache-Control', 'max-age=300, public');
});

test('the super admin adds pictures to each place and the app is told where to find them, in order', function () {
    $super = superAdmin();
    $this->actingAs($super)->get(route('super-admin.app-media.index'))->assertOk()->assertSee('Opening photos')->assertSee('Welcome screen photos')->assertSee('Home banner slider')
        ->assertSee('using the photos built into it')->assertSee('opens straight to Home, without the opening photos');

    $this->actingAs($super)->post(route('super-admin.app-media.store'), ['placement' => 'opening', 'photos' => [appPhoto('a.jpg'), appPhoto('b.jpg')]])
        ->assertRedirect()->assertSessionHas('success', '2 pictures were added.');
    $this->actingAs($super)->post(route('super-admin.app-media.store'), ['placement' => 'welcome', 'photos' => [appPhoto('c.jpg')]])->assertRedirect();
    $this->actingAs($super)->post(route('super-admin.app-media.store'), ['placement' => 'banner', 'photos' => [appPhoto('d.jpg', 1400, 900)]])->assertRedirect();

    $data = $this->getJson(route('api.v1.app.media'))->assertOk()->json('data');
    expect($data['opening'])->toHaveCount(2)->and($data['welcome'])->toHaveCount(1)->and($data['banner'])->toHaveCount(1)
        ->and($data['opening'][0]['url'])->toContain('pill')->and($data['welcome'][0]['url'])->toContain('full')->and($data['banner'][0]['url'])->toContain('banner')
        ->and($data['opening'][0]['id'])->toBeLessThan($data['opening'][1]['id'])->and($data['version'])->toBeString()->not->toBe('0');
    expect(AppPhoto::count())->toBe(4)->and(AuditLog::where('action', 'app_media.added')->count())->toBe(3);
});

test('the banner pictures carry a title and a sentence, a picture can be switched off, moved and removed', function () {
    $super = superAdmin();
    foreach (['one.jpg', 'two.jpg', 'three.jpg'] as $name) {
        $this->actingAs($super)->post(route('super-admin.app-media.store'), ['placement' => 'banner', 'photos' => [appPhoto($name, 1400, 900)]])->assertRedirect();
    }
    [$one, $two, $three] = AppPhoto::orderBy('position')->get()->all();

    $this->actingAs($super)->put(route('super-admin.app-media.update', $one), ['title' => 'Worship together', 'subtitle' => 'Every Sunday', 'is_active' => 1])->assertRedirect();
    $this->actingAs($super)->put(route('super-admin.app-media.update', $two), ['title' => 'Hidden'])->assertRedirect(); // switch not sent: off
    $first = collect($this->getJson(route('api.v1.app.media'))->json('data.banner'));
    expect($first->pluck('id')->all())->toBe([$one->id, $three->id])->and($first[0])->toMatchArray(['title' => 'Worship together', 'subtitle' => 'Every Sunday']);

    $this->actingAs($super)->post(route('super-admin.app-media.move', $three), ['direction' => 'up'])->assertRedirect();
    $this->actingAs($super)->post(route('super-admin.app-media.move', $one), ['direction' => 'up'])->assertRedirect(); // already first: nothing happens
    expect(AppPhoto::orderBy('position')->pluck('id')->all())->toBe([$one->id, $three->id, $two->id]);

    $before = $this->getJson(route('api.v1.app.media'))->json('data.version');
    $this->actingAs($super)->delete(route('super-admin.app-media.destroy', $one))->assertRedirect();
    expect(AppPhoto::count())->toBe(2)->and($this->getJson(route('api.v1.app.media'))->json('data.version'))->not->toBe($before);
    Storage::disk('public')->assertDirectoryEmpty(''.($one->id)); // its files are gone
});

test('a place holds twelve pictures, and only pictures are accepted', function () {
    $super = superAdmin();
    $this->actingAs($super)->post(route('super-admin.app-media.store'), ['placement' => 'welcome', 'photos' => collect(range(1, 12))->map(fn ($i) => appPhoto("p{$i}.jpg", 600, 900))->all()])->assertRedirect();
    expect(AppPhoto::count())->toBe(12);

    $this->actingAs($super)->post(route('super-admin.app-media.store'), ['placement' => 'welcome', 'photos' => [appPhoto('extra.jpg')]])->assertSessionHasErrors('photos');
    $this->actingAs($super)->post(route('super-admin.app-media.store'), ['placement' => 'opening', 'photos' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')]])->assertSessionHasErrors('photos.0');
    $this->actingAs($super)->post(route('super-admin.app-media.store'), ['placement' => 'elsewhere', 'photos' => [appPhoto()]])->assertSessionHasErrors('placement');
    expect(AppPhoto::count())->toBe(12);
});

test('only the super admin controls the app media', function () {
    $church = Church::factory()->create();
    $admin = member();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);
    $photo = AppPhoto::create(['placement' => 'banner', 'position' => 1]);

    foreach ([$admin, member()] as $who) {
        $this->actingAs($who)->get(route('super-admin.app-media.index'))->assertForbidden();
        $this->actingAs($who)->post(route('super-admin.app-media.store'), ['placement' => 'opening', 'photos' => [appPhoto()]])->assertForbidden();
        $this->actingAs($who)->put(route('super-admin.app-media.update', $photo), ['title' => 'x'])->assertForbidden();
        $this->actingAs($who)->delete(route('super-admin.app-media.destroy', $photo))->assertForbidden();
    }
    expect(AppPhoto::count())->toBe(1);
});
