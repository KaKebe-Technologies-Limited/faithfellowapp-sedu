<?php

use App\Models\Church;
use App\Models\RadioStation;
use App\Models\RadioTrack;
use App\Models\Sermon;
use App\Services\RadioService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| MPEG audio (.mpeg, .mpga, .mpg, .mp2) is accepted next to MP3, M4A, AAC and WAV: on the radio and podcast channels, as a sermon or devotional recording,
| and it is served back with the right type. A video that is only called .mpeg is not.
*/

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);
});

/** About ten seconds of MPEG audio frames (what a .mpeg / .mpga / .mp2 audio file holds). */
function mpegAudioBytes(int $frames = 400): string
{
    return str_repeat("\xFF\xFB\x90\x00".str_repeat("\x00", 413), $frames);
}

function mpegAudio(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, mpegAudioBytes());
}

test('the radio takes MPEG audio under every name it goes by, and plays it as audio/mpeg', function (string $name, string $extension) {
    $this->actingAs(superAdmin())->postJson(route('super-admin.radio.store'), ['track' => mpegAudio($name)])->assertOk()->assertJson(['ok' => true]);

    $track = RadioTrack::sole();
    expect($track->mime)->toBe('audio/mpeg')->and($track->file_path)->toEndWith('.'.$extension)->and($track->duration_seconds)->toBeGreaterThan(8)
        ->and($track->title)->toBe('Sunday Teaching')->and($track->artist)->toBe('Pastor Okello');
    Storage::disk('local')->assertExists($track->file_path);
    $this->get(route('radio.stream', $track))->assertSuccessful()->assertHeader('Content-Type', 'audio/mpeg');
})->with([
    'mpeg' => ['Sunday Teaching - Pastor Okello.mpeg', 'mpeg'],
    'mpga' => ['Sunday Teaching - Pastor Okello.mpga', 'mpga'],
    'mpg' => ['Sunday Teaching - Pastor Okello.MPG', 'mpg'],
    'mp2' => ['Sunday Teaching - Pastor Okello.mp2', 'mp2'],
]);

test('a church adds MPEG audio to its podcast channel, and the upload form says it is allowed', function () {
    $admin = member();
    $church = Church::factory()->create();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);

    $this->actingAs($admin)->get(route('church-admin.radio.index', $church))->assertOk()->assertSee('MPEG audio')->assertSee('.mpeg', false);

    $station = RadioStation::forChurch($church);
    $radio = app(RadioService::class);
    $file = tempnam(sys_get_temp_dir(), 'mpeg');
    file_put_contents($file, mpegAudioBytes());
    $track = $radio->addFile($file, 'Faith at Work.mpeg', $admin, null, null, $station);

    expect($track->station_id)->toBe($station->id)->and($track->mime)->toBe('audio/mpeg')->and($radio->tracks($station))->toHaveCount(1);
    @unlink($file);
});

test('a file that is not audio is still refused, and the reason names MPEG', function () {
    $this->actingAs(superAdmin())->postJson(route('super-admin.radio.store'), ['track' => UploadedFile::fake()->createWithContent('notes.txt', 'hello')])
        ->assertStatus(422)->assertJsonPath('message', 'Use an MP3, MPEG, M4A, AAC or WAV file.');
    $this->actingAs(superAdmin())->postJson(route('super-admin.radio.store'), ['track' => UploadedFile::fake()->createWithContent('broken.mpeg', str_repeat('x', 5000))])
        ->assertStatus(422)->assertJsonPath('message', 'This does not look like a song file we can read.');
    expect(RadioTrack::count())->toBe(0);
});

test('a church can record a sermon as an .mpeg audio file, and it is served from /storage', function () {
    $admin = member();
    $church = Church::factory()->create();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);

    $this->actingAs($admin)->post(route('church-admin.sermons.store', $church), [
        'title' => 'Walking by faith', 'language' => 'en', 'status' => 'draft', 'visibility' => 'public', 'audio' => mpegAudio('walking-by-faith.mpeg')->mimeType('audio/mpeg'), // a real upload is typed by what is in it; a test file by its name
    ])->assertSessionHasNoErrors();

    $media = Sermon::where('title', 'Walking by faith')->firstOrFail()->getFirstMedia('audio');
    expect($media)->not->toBeNull()->and($media->file_name)->toEndWith('.mpeg')->and($media->mime_type)->toBe('audio/mpeg');
    $this->get('/storage/'.$media->id.'/'.$media->file_name)->assertOk();
});
