<?php

use App\Models\Church;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Songs and other audio: the platform admin may upload up to 400 MB per file, everyone else up to 150 MB. The tests lower the
| numbers to 1 MB and 5 MB so that no giant file has to be made.
*/

beforeEach(function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public', 'faithfellows.limits.max_audio_mb' => 1, 'faithfellows.limits.max_admin_audio_mb' => 5]);
});

/** A real MP3 of about 1.2 MB (ID3 + MPEG frames), so it is sniffed as audio and is over the 1 MB limit. */
function bigMp3(string $name = 'long-sermon.mp3'): UploadedFile
{
    $frame = "\xFF\xFB\x90\x64".str_repeat("\x00", 413);

    return UploadedFile::fake()->createWithContent($name, "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat($frame, 3000));
}

function audioChurch(): array
{
    $admin = member();
    $church = Church::factory()->create();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);

    return [$church, $admin];
}

test('the audio limit is bigger for the platform admin than for everyone else', function () {
    config(['faithfellows.limits.max_audio_mb' => 150, 'faithfellows.limits.max_admin_audio_mb' => 400]);

    expect(ff_audio_upload_mb(superAdmin()))->toBe(400)
        ->and(ff_audio_upload_mb(member()))->toBe(150)
        ->and(ff_audio_upload_mb())->toBe(150);
});

test('church staff cannot upload a sermon recording over their limit, the platform admin can', function () {
    [$church, $admin] = audioChurch();
    $post = ['title' => 'Long sermon', 'language' => 'en', 'status' => 'draft', 'visibility' => 'public'];

    $this->actingAs($admin)->post(route('church-admin.sermons.store', $church), $post + ['audio' => bigMp3()])->assertSessionHasErrors('audio');

    $this->actingAs(superAdmin())->post(route('church-admin.sermons.store', $church), $post + ['audio' => bigMp3()])->assertSessionHasNoErrors();
});

test('the media library holds church staff to the audio limit but not images', function () {
    [$church, $admin] = audioChurch();

    $this->actingAs($admin)->post(route('church-admin.media.store', $church), ['files' => [bigMp3()]])->assertSessionHasErrors('files.0');

    $picture = UploadedFile::fake()->image('cover.jpg', 3000, 3000)->size(1500);
    $this->actingAs($admin)->post(route('church-admin.media.store', $church), ['files' => [$picture]])->assertSessionHasNoErrors();

    $this->actingAs(superAdmin())->post(route('church-admin.media.store', $church), ['files' => [bigMp3()]])->assertSessionHasNoErrors();
});

test('the sermon form tells staff their audio limit', function () {
    [$church, $admin] = audioChurch();

    $this->actingAs($admin)->get(route('church-admin.sermons.create', $church))->assertOk()->assertSee('Audio up to 1 MB');
});
