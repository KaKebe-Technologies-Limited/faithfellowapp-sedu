<?php

use App\Models\PlatformSetting;
use App\Models\RadioTrack;
use App\Services\RadioService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
| The gospel radio: songs added by the super admin play round the clock in the app, in a loop, and everyone hears the
| same song at the same moment because "now playing" comes from the clock.
*/

beforeEach(function () {
    Storage::fake('local');
    Cache::forget('platform_settings');
});

/** A tiny valid MP3: MPEG-1 Layer III, 128 kbit/s, 44.1 kHz; each frame is 417 bytes and lasts 26.1 ms. */
function tinyMp3(int $frames = 400): string
{
    $frame = "\xFF\xFB\x90\x00".str_repeat("\x00", 413);

    return str_repeat($frame, $frames);
}

function songFile(string $name = 'Amazing Grace - Test Choir.mp3', int $frames = 400): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, tinyMp3($frames));
}

function station(array $durations): array
{
    return collect($durations)->map(fn ($seconds, $i) => RadioTrack::create([
        'title' => 'Song '.chr(65 + $i), 'artist' => 'Choir', 'file_path' => "radio/{$i}.mp3", 'duration_seconds' => $seconds, 'position' => $i + 1,
    ]))->all();
}

function atStation(int $secondsSinceEpoch): Carbon
{
    return Carbon::parse(RadioService::EPOCH.' UTC')->addSeconds($secondsSinceEpoch);
}

// ── The station clock ───────────────────────────────────────────────────────────────────────────────────

test('the station says which song is on and how far into it, from the clock alone', function () {
    [$a, $b, $c] = station([100, 200, 50]); // one round lasts 350 seconds
    $radio = app(RadioService::class);

    $now = $radio->nowPlaying(atStation(0));
    expect($now['track']['id'])->toBe($a->id)->and($now['offset'])->toBe(0);

    $now = $radio->nowPlaying(atStation(150));
    expect($now['track']['id'])->toBe($b->id)->and($now['offset'])->toBe(50)
        ->and($now['upcoming'][0]['id'])->toBe($c->id)->and($now['upcoming'][1]['id'])->toBe($a->id)
        ->and($now['recent'][0]['id'])->toBe($a->id);

    $now = $radio->nowPlaying(atStation(340));
    expect($now['track']['id'])->toBe($c->id)->and($now['offset'])->toBe(40);

    // After a round it starts again, and a day later it is still in step.
    expect($radio->nowPlaying(atStation(350))['track']['id'])->toBe($a->id)
        ->and($radio->nowPlaying(atStation(350 * 247 + 120))['track']['id'])->toBe($b->id)
        ->and($radio->nowPlaying(atStation(350 * 247 + 120))['offset'])->toBe(20);
});

test('the song that started most recently shows as just played, with when it began', function () {
    [$a, $b] = station([100, 200]);

    $now = app(RadioService::class)->nowPlaying(atStation(130));

    expect($now['started_at'])->toBe(atStation(100)->toIso8601String())
        ->and($now['ends_at'])->toBe(atStation(300)->toIso8601String())
        ->and($now['recent'][0]['id'])->toBe($a->id)->and($now['recent'][0]['played_at'])->toBe(atStation(0)->toIso8601String());
});

test('songs switched off, and a switched-off radio, drop out', function () {
    [$a, $b] = station([100, 200]);
    $a->update(['is_active' => false]);
    $radio = app(RadioService::class);

    expect($radio->nowPlaying(atStation(10))['track']['id'])->toBe($b->id)->and($radio->loopSeconds())->toBe(200);

    $radio->saveSettings('Praise FM', false);
    expect($radio->nowPlaying())->toBeNull()->and($radio->name())->toBe('Praise FM');
});

test('a radio with no songs is simply off', function () {
    expect(app(RadioService::class)->nowPlaying())->toBeNull();
});

// ── The app's endpoint and the song files ───────────────────────────────────────────────────────────────

test('the app asks what is on air and gets the song, where to start and what follows', function () {
    $member = member();
    $this->getJson(route('api.v1.radio'))->assertUnauthorized();
    Sanctum::actingAs($member, ['member']);

    $this->getJson(route('api.v1.radio'))->assertOk()->assertJsonPath('data.enabled', false)->assertJsonPath('data.now', null);

    station([100, 200, 50]);
    $data = $this->getJson(route('api.v1.radio'))->assertOk()->json('data');
    expect($data['enabled'])->toBeTrue()->and($data['name'])->toBe('FaithFellows Radio')->and($data['songs'])->toBe(3)
        ->and($data['now']['track'])->toHaveKeys(['id', 'title', 'artist', 'duration', 'cover_url', 'stream_url'])
        ->and($data['now']['upcoming'])->toHaveCount(4)->and($data['now']['offset'])->toBeInt()->and($data['server_time'])->not->toBeNull();
});

test('songs stream with byte ranges, so a phone can join part-way, and covers fall back to a drawn picture', function () {
    Storage::disk('local')->put('radio/one.mp3', tinyMp3(50));
    $track = RadioTrack::create(['title' => 'One', 'file_path' => 'radio/one.mp3', 'duration_seconds' => 1, 'position' => 1]);

    $this->get(route('radio.stream', $track), ['Range' => 'bytes=0-99'])->assertStatus(206)->assertHeader('Accept-Ranges', 'bytes')->assertHeader('Content-Type', 'audio/mpeg');
    $this->get(route('radio.stream', $track))->assertOk();
    $this->get(route('radio.cover', $track))->assertNotFound();
    expect($track->coverUrl())->toContain('/art/radio/'.$track->id);
    $this->get($track->coverUrl())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});

// ── The super admin's page ──────────────────────────────────────────────────────────────────────────────

test('only the super admin can open the radio page or change the songs', function () {
    $this->actingAs(member())->get(route('super-admin.radio.index'))->assertForbidden();
    $this->actingAs(member())->post(route('super-admin.radio.store'), ['track' => songFile()])->assertForbidden();

    $this->actingAs(superAdmin())->get(route('super-admin.radio.index'))->assertOk()->assertSee('Gospel radio')->assertSee('No songs yet');
});

test('the super admin adds a song and its name, artist and length are read from the file', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->postJson(route('super-admin.radio.store'), ['track' => songFile('Amazing Grace - Test Choir (128 Kbps).mp3')])
        ->assertOk()->assertJson(['ok' => true, 'title' => 'Amazing Grace']);

    $track = RadioTrack::sole();
    expect($track->artist)->toBe('Test Choir')->and($track->duration_seconds)->toBeGreaterThan(8)->and($track->duration_seconds)->toBeLessThan(12)
        ->and($track->position)->toBe(1)->and($track->is_active)->toBeTrue()->and($track->uploaded_by)->toBe($admin->id);
    Storage::disk('local')->assertExists($track->file_path);

    $this->actingAs($admin)->get(route('super-admin.radio.index'))->assertOk()->assertSee('Amazing Grace')->assertSee('Test Choir');
});

test('something that is not a song is refused with a reason', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->postJson(route('super-admin.radio.store'), ['track' => UploadedFile::fake()->createWithContent('notes.txt', 'hello')])
        ->assertStatus(422)->assertJsonPath('message', 'Use an MP3, M4A, AAC or WAV file.');
    $this->actingAs($admin)->postJson(route('super-admin.radio.store'), ['track' => UploadedFile::fake()->createWithContent('broken.mp3', str_repeat('x', 5000))])
        ->assertStatus(422)->assertJsonPath('message', 'This does not look like a song file we can read.');
    $this->actingAs($admin)->postJson(route('super-admin.radio.store'), [])->assertStatus(422);

    expect(RadioTrack::count())->toBe(0);
});

test('the super admin renames, reorders, switches off and removes songs', function () {
    $admin = superAdmin();
    foreach (['One - A.mp3', 'Two - B.mp3', 'Three - C.mp3'] as $name) {
        $this->actingAs($admin)->postJson(route('super-admin.radio.store'), ['track' => songFile($name)])->assertOk();
    }
    [$one, $two, $three] = RadioTrack::orderBy('position')->get()->all();

    $this->actingAs($admin)->put(route('super-admin.radio.update', $two), ['title' => 'Second Song', 'artist' => 'The Choir'])->assertRedirect();
    expect($two->fresh()->title)->toBe('Second Song')->and($two->fresh()->artist)->toBe('The Choir');

    $this->actingAs($admin)->post(route('super-admin.radio.move', $three), ['direction' => 'up'])->assertRedirect();
    expect(RadioTrack::orderBy('position')->pluck('id')->all())->toBe([$one->id, $three->id, $two->id]);
    $this->actingAs($admin)->post(route('super-admin.radio.move', $one), ['direction' => 'up'])->assertRedirect(); // already first: nothing changes
    expect(RadioTrack::orderBy('position')->pluck('id')->first())->toBe($one->id);

    $this->actingAs($admin)->put(route('super-admin.radio.update', $one), ['is_active' => 0])->assertRedirect();
    expect(app(RadioService::class)->tracks()->pluck('id')->all())->toBe([$three->id, $two->id]);

    $file = $three->file_path;
    $this->actingAs($admin)->delete(route('super-admin.radio.destroy', $three))->assertRedirect();
    Storage::disk('local')->assertMissing($file);
    expect(RadioTrack::orderBy('position')->pluck('position')->all())->toBe([1, 2]);
});

test('the station can be renamed and switched off', function () {
    $admin = superAdmin();
    station([100]);

    $this->actingAs($admin)->put(route('super-admin.radio.settings'), ['name' => 'Lira Gospel 24/7', 'enabled' => 1])->assertRedirect();
    expect(PlatformSetting::get('radio_name'))->toBe('Lira Gospel 24/7');

    $this->actingAs($admin)->put(route('super-admin.radio.settings'), ['name' => 'Lira Gospel 24/7'])->assertRedirect();
    expect(app(RadioService::class)->enabled())->toBeFalse();
    Sanctum::actingAs(member(), ['member']);
    $this->getJson(route('api.v1.radio'))->assertJsonPath('data.enabled', false);
});

test('songs can be added from the command line too', function () {
    $path = tempnam(sys_get_temp_dir(), 'song');
    rename($path, $path .= '.mp3');
    file_put_contents($path, tinyMp3());

    $this->artisan('radio:add', ['files' => [$path], '--title' => 'Great Is Thy Faithfulness', '--artist' => 'Old Hymn'])->assertSuccessful();
    $this->artisan('radio:add', ['files' => ['/no/such/file.mp3']])->assertFailed();

    expect(RadioTrack::sole()->title)->toBe('Great Is Thy Faithfulness')->and(RadioTrack::sole()->artist)->toBe('Old Hymn');
    @unlink($path);
});

// ── Big songs arrive in pieces ──────────────────────────────────────────────────────────────────────────

/** Sends a song to the radio in pieces, the way the page does; returns the last response. */
function sendInPieces($test, $admin, string $content, string $name, int $pieces, string $id = 'abcdef0123456789abcd')
{
    $size = (int) ceil(strlen($content) / $pieces);
    $response = null;
    for ($i = 0; $i < $pieces; $i++) {
        $response = $test->actingAs($admin)->postJson(route('super-admin.radio.chunk'), [
            'upload_id' => $id, 'index' => $i, 'total' => $pieces, 'name' => $name,
            'chunk' => UploadedFile::fake()->createWithContent('piece', substr($content, $i * $size, $size)),
        ]);
        if (! $response->isOk()) {
            break; // like the page: it stops at the first piece the server refuses
        }
    }

    return $response;
}

test('a song sent in pieces is put together and added to the radio', function () {
    $admin = superAdmin();

    $last = sendInPieces($this, $admin, tinyMp3(400), 'Great Is Thy Faithfulness - Test Choir.mp3', 3)->assertOk()->assertJson(['ok' => true, 'title' => 'Great Is Thy Faithfulness']);

    $track = RadioTrack::sole();
    expect($track->artist)->toBe('Test Choir')->and($track->size)->toBe(strlen(tinyMp3(400)))->and($track->uploaded_by)->toBe($admin->id);
    Storage::disk('local')->assertExists($track->file_path);
    expect(Storage::disk('local')->directories('radio-uploads'))->toBe([]);
});

test('the pieces of a song are not accepted past the platform admin limit', function () {
    config(['faithfellows.limits.max_admin_audio_mb' => 1]);
    $admin = superAdmin();

    // 3 pieces of about 0.5 MB: the third one takes the song over 1 MB.
    $content = str_repeat('x', 1_600_000);
    $response = sendInPieces($this, $admin, $content, 'huge.mp3', 3);

    $response->assertStatus(422)->assertJsonPath('message', 'A song can be up to 1 MB.');
    expect(RadioTrack::count())->toBe(0)->and(Storage::disk('local')->directories('radio-uploads'))->toBe([]);
});

test('a song with a missing piece is refused and cleaned up', function () {
    $admin = superAdmin();

    // Only the last of two pieces arrives.
    $this->actingAs($admin)->postJson(route('super-admin.radio.chunk'), [
        'upload_id' => 'abcdef0123456789abcd', 'index' => 1, 'total' => 2, 'name' => 'lost.mp3',
        'chunk' => UploadedFile::fake()->createWithContent('piece', tinyMp3(10)),
    ])->assertStatus(422);

    expect(RadioTrack::count())->toBe(0)->and(Storage::disk('local')->directories('radio-uploads'))->toBe([]);
});

test('only the super admin can send pieces, and a piece needs a safe upload id', function () {
    $piece = ['upload_id' => 'abcdef0123456789abcd', 'index' => 0, 'total' => 1, 'name' => 'a.mp3', 'chunk' => UploadedFile::fake()->createWithContent('piece', tinyMp3(10))];

    $this->actingAs(member())->postJson(route('super-admin.radio.chunk'), $piece)->assertForbidden();
    $this->actingAs(superAdmin())->postJson(route('super-admin.radio.chunk'), [...$piece, 'upload_id' => '../../etc'])->assertStatus(422);
});

test('the radio page tells the platform admin the song limit', function () {
    $this->actingAs(superAdmin())->get(route('super-admin.radio.index'))->assertOk()->assertSee('Up to 400 MB per song')
        // The progress card for each song: a ring, the FaithFellows mark inside it, and the percentage.
        ->assertSee('id="radio-card"', false)->assertSee('ff-upload-ring', false)->assertSee('ff-logo-mark', false);
});
