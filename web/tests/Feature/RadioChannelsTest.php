<?php

use App\Models\Church;
use App\Models\RadioStation;
use App\Models\RadioTrack;
use App\Services\RadioService;
use App\Support\StorageQuota;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
| Radio channels: the platform's Gospel radio and Podcast radio (filled by the super admin) and one channel for each church (filled by the church).
| Each plays round the clock, in a loop; members hear the platform's channels and the channels of the churches they follow.
*/

beforeEach(function () {
    Storage::fake('local');
    Cache::forget('platform_settings');
});

/** A tiny valid MP3 (about 10 seconds), as in the radio tests. */
function channelMp3(int $frames = 400): string
{
    return str_repeat("\xFF\xFB\x90\x00".str_repeat("\x00", 413), $frames);
}

function channelFile(string $name = 'Faith and Work - Pastor Okello.mp3', int $frames = 400): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, channelMp3($frames));
}

/** A church with an admin who may manage media, and a member who follows it. @return array{0: Church, 1: App\Models\User, 2: App\Models\User} */
function channelChurch(): array
{
    $admin = member();
    $church = Church::factory()->create();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);
    $follower = member();
    $church->subscriptions()->create(['user_id' => $follower->id, 'status' => 'active', 'subscribed_at' => now()]);

    return [$church, $admin, $follower];
}

function sendPieces($test, $user, string $route, array $params, string $content, string $name, int $pieces = 2, string $id = 'abcdef0123456789abcd', array $extra = [])
{
    $size = (int) ceil(strlen($content) / $pieces);
    $response = null;
    for ($i = 0; $i < $pieces; $i++) {
        $response = $test->actingAs($user)->postJson(route($route, $params), [
            'upload_id' => $id, 'index' => $i, 'total' => $pieces, 'name' => $name,
            'chunk' => UploadedFile::fake()->createWithContent('piece', substr($content, $i * $size, $size)),
        ] + $extra);
        if (! $response->isOk()) {
            break;
        }
    }

    return $response;
}

// ── The platform's Podcast radio ────────────────────────────────────────────────────────────────────────

test('the super admin fills the Podcast radio on its own, apart from the Gospel radio', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->postJson(route('super-admin.radio.store'), ['track' => channelFile('Hope in Hard Times - Pastor Okello.mp3'), 'station' => 'podcast'])
        ->assertOk()->assertJson(['ok' => true, 'title' => 'Hope in Hard Times']);
    $this->actingAs($admin)->postJson(route('super-admin.radio.store'), ['track' => channelFile('Amazing Grace - Test Choir.mp3')])->assertOk();

    $radio = app(RadioService::class);
    expect(RadioTrack::ofStation(RadioStation::podcast())->pluck('title')->all())->toBe(['Hope in Hard Times'])
        ->and(RadioTrack::ofStation(RadioStation::gospel())->pluck('title')->all())->toBe(['Amazing Grace'])
        ->and($radio->nowPlaying(null, 4, 5, RadioStation::podcast())['track']['title'])->toBe('Hope in Hard Times')
        ->and($radio->nowPlaying()['track']['title'])->toBe('Amazing Grace');
});

test('the Podcast radio loops round the clock like the Gospel radio', function () {
    $radio = app(RadioService::class);
    $podcast = RadioStation::podcast();
    foreach ([100, 200] as $i => $seconds) {
        RadioTrack::create(['station_id' => $podcast->id, 'title' => 'Talk '.($i + 1), 'file_path' => "radio/t{$i}.mp3", 'duration_seconds' => $seconds, 'position' => $i + 1]);
    }
    $at = Carbon\Carbon::parse(RadioService::EPOCH.' UTC');

    expect($radio->nowPlaying($at->copy()->addSeconds(50), 4, 5, $podcast)['track']['title'])->toBe('Talk 1')
        ->and($radio->nowPlaying($at->copy()->addSeconds(150), 4, 5, $podcast)['track']['title'])->toBe('Talk 2')
        ->and($radio->nowPlaying($at->copy()->addSeconds(300 + 50), 4, 5, $podcast)['track']['title'])->toBe('Talk 1'); // round two, a whole round later
});

test('the super admin page has a tab for each platform channel and the pieces go to the right one', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->get(route('super-admin.radio.index', ['station' => 'podcast']))->assertOk()
        ->assertSee('Podcast radio')->assertSee('Gospel radio')->assertSee('No episodes yet')->assertSee('Up to 400 MB per episode');

    sendPieces($this, $admin, 'super-admin.radio.chunk', [], channelMp3(), 'Sunday Teaching - Pastor Okello.mp3', 2, 'abcdef0123456789abcd', ['station' => 'podcast'])->assertOk()->assertJson(['ok' => true]);
    expect(RadioTrack::ofStation(RadioStation::podcast())->count())->toBe(1)->and(RadioTrack::ofStation(RadioStation::gospel())->count())->toBe(0);
});

// ── A church's own channel ──────────────────────────────────────────────────────────────────────────────

test('a church adds its own episodes and they join its channel', function () {
    [$church, $admin] = channelChurch();

    $this->actingAs($admin)->get(route('church-admin.radio.index', $church))->assertOk()->assertSee('Radio channel')->assertSee('No episodes yet')->assertSee('Up to 150 MB per episode');

    sendPieces($this, $admin, 'church-admin.radio.chunk', [$church], channelMp3(), 'Faith and Work - Pastor Okello.mp3')->assertOk()->assertJson(['ok' => true, 'title' => 'Faith and Work']);

    $station = RadioStation::where('church_id', $church->id)->sole();
    $track = RadioTrack::where('station_id', $station->id)->sole();
    expect($station->slug)->toBe('church-'.$church->id)->and($station->kind)->toBe('talk')->and($track->uploaded_by)->toBe($admin->id)
        ->and(RadioTrack::ofStation(RadioStation::gospel())->count())->toBe(0)
        ->and(StorageQuota::usedBytes($church))->toBeGreaterThanOrEqual($track->size);
});

test('a church can rename, reorder, switch off and remove its own episodes, and nobody else can touch them', function () {
    [$church, $admin] = channelChurch();
    foreach (['One - A.mp3', 'Two - B.mp3'] as $i => $name) {
        sendPieces($this, $admin, 'church-admin.radio.chunk', [$church], channelMp3(), $name, 1, 'abcdef0123456789ab'.$i.'0')->assertOk();
    }
    [$one, $two] = RadioTrack::orderBy('position')->get()->all();

    $this->actingAs($admin)->put(route('church-admin.radio.update', [$church, $one->id]), ['title' => 'First sermon'])->assertRedirect();
    $this->actingAs($admin)->post(route('church-admin.radio.move', [$church, $one->id]), ['direction' => 'down'])->assertRedirect();
    $this->actingAs($admin)->put(route('church-admin.radio.update', [$church, $two->id]), ['is_active' => 0])->assertRedirect();
    expect($one->fresh()->title)->toBe('First sermon')->and($one->fresh()->position)->toBe(2)->and($two->fresh()->position)->toBe(1)->and($two->fresh()->is_active)->toBeFalse();

    // Another church's admin, and an ordinary member, cannot reach this channel's tracks.
    [$other, $otherAdmin] = channelChurch();
    $this->actingAs($otherAdmin)->put(route('church-admin.radio.update', [$other, $one->id]), ['title' => 'Stolen'])->assertNotFound();
    $this->actingAs($otherAdmin)->delete(route('church-admin.radio.destroy', [$other, $one->id]))->assertNotFound();
    $this->actingAs($otherAdmin)->get(route('church-admin.radio.index', $church))->assertForbidden();
    $this->actingAs(member())->get(route('church-admin.radio.index', $church))->assertForbidden();

    $this->actingAs($admin)->delete(route('church-admin.radio.destroy', [$church, $one->id]))->assertRedirect();
    expect(RadioTrack::count())->toBe(1);
    Storage::disk('local')->assertMissing($one->file_path);
});

test('a church audio file is held to 150 MB and to the church storage', function () {
    [$church, $admin] = channelChurch();
    config(['faithfellows.limits.max_audio_mb' => 1]);

    // Pieces of 0.6 MB each: the second one takes the file over 1 MB.
    sendPieces($this, $admin, 'church-admin.radio.chunk', [$church], str_repeat('x', 1_300_000), 'big.mp3', 2)->assertStatus(422)->assertJsonPath('message', 'An episode can be up to 1 MB.');
    expect(RadioTrack::count())->toBe(0);

    // A church whose storage is full cannot add more.
    config(['faithfellows.limits.max_audio_mb' => 150, 'faithfellows.limits.storage_mb' => 1]);
    RadioTrack::create(['station_id' => RadioStation::forChurch($church)->id, 'title' => 'Old', 'file_path' => 'radio/old.mp3', 'size' => 1_048_000, 'duration_seconds' => 60, 'position' => 1]);
    sendPieces($this, $admin, 'church-admin.radio.chunk', [$church], channelMp3(), 'more.mp3', 1)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'exceed your church'));
});

// ── What members hear, and who can hear what ────────────────────────────────────────────────────────────

test('members hear the platform channels and the channels of the churches they follow, nothing else', function () {
    [$church, $admin, $follower] = channelChurch();
    [$otherChurch, $otherAdmin] = channelChurch();

    $this->actingAs(superAdmin())->postJson(route('super-admin.radio.store'), ['track' => channelFile('Amazing Grace - Test Choir.mp3')])->assertOk();
    $this->actingAs(superAdmin())->postJson(route('super-admin.radio.store'), ['track' => channelFile('Hope in Hard Times - Pastor Okello.mp3'), 'station' => 'podcast'])->assertOk();
    sendPieces($this, $admin, 'church-admin.radio.chunk', [$church], channelMp3(), 'Faith and Work - Pastor Okello.mp3', 1)->assertOk();
    sendPieces($this, $otherAdmin, 'church-admin.radio.chunk', [$otherChurch], channelMp3(), 'Their Teaching - Pastor Other.mp3', 1, 'zyxwvu9876543210zyxw')->assertOk();

    Sanctum::actingAs($follower, ['member']);
    $list = $this->getJson(route('api.v1.radio.stations'))->assertOk()->json('data');
    expect(collect($list)->pluck('slug')->all())->toBe(['gospel', 'podcast', 'church-'.$church->id])
        ->and($list[2]['church']['name'])->toBe($church->name)->and($list[2]['songs'])->toBe(1);

    $this->getJson(route('api.v1.radio.station', 'church-'.$church->id))->assertOk()->assertJsonPath('data.now.track.title', 'Faith and Work')->assertJsonPath('data.kind', 'talk');
    $this->getJson(route('api.v1.radio.station', 'podcast'))->assertOk()->assertJsonPath('data.now.track.title', 'Hope in Hard Times');
    $this->getJson(route('api.v1.radio.station', 'church-'.$otherChurch->id))->assertNotFound();
    $this->getJson(route('api.v1.radio.station', 'no-such-channel'))->assertNotFound();

    // The old call is still the Gospel radio, in the same shape as before.
    $this->getJson(route('api.v1.radio'))->assertOk()->assertJsonPath('data.name', 'FaithFellows Radio')->assertJsonPath('data.now.track.title', 'Amazing Grace')
        ->assertJsonStructure(['data' => ['enabled', 'name', 'tagline', 'now', 'songs', 'server_time']]);
});

test('a channel with nothing in it, or switched off, is not offered to members', function () {
    [$church, $admin, $follower] = channelChurch();
    $this->actingAs($admin)->put(route('church-admin.radio.settings', $church), ['name' => 'Grace Teaching', 'enabled' => 1])->assertRedirect();
    Sanctum::actingAs($follower, ['member']);

    expect(collect($this->getJson(route('api.v1.radio.stations'))->json('data'))->pluck('slug')->all())->not->toContain('church-'.$church->id);

    sendPieces($this, $admin, 'church-admin.radio.chunk', [$church], channelMp3(), 'Faith and Work - Pastor Okello.mp3', 1)->assertOk();
    $this->actingAs($follower);
    $names = collect($this->getJson(route('api.v1.radio.stations'))->json('data'))->pluck('name', 'slug');
    expect($names['church-'.$church->id])->toBe('Grace Teaching');

    $this->actingAs($admin)->put(route('church-admin.radio.settings', $church), ['name' => 'Grace Teaching'])->assertRedirect(); // switch not sent: off
    expect(collect($this->getJson(route('api.v1.radio.stations'))->json('data'))->pluck('slug')->all())->not->toContain('church-'.$church->id);
});

test('the super admin can switch off a church channel and the church cannot switch it back on', function () {
    [$church, $admin, $follower] = channelChurch();
    sendPieces($this, $admin, 'church-admin.radio.chunk', [$church], channelMp3(), 'Faith and Work - Pastor Okello.mp3', 1)->assertOk();
    $station = RadioStation::where('church_id', $church->id)->sole();

    $this->actingAs(superAdmin())->get(route('super-admin.radio.index'))->assertOk()->assertSee('Church channels')->assertSee($station->name);
    $this->actingAs(superAdmin())->put(route('super-admin.radio.block', $station))->assertRedirect();
    expect($station->fresh()->is_blocked)->toBeTrue();

    $this->actingAs($admin)->put(route('church-admin.radio.settings', $church), ['name' => 'Back again', 'enabled' => 1])->assertRedirect();
    Sanctum::actingAs($follower, ['member']);
    expect(collect($this->getJson(route('api.v1.radio.stations'))->json('data'))->pluck('slug')->all())->not->toContain('church-'.$church->id);
    sendPieces($this, $admin, 'church-admin.radio.chunk', [$church], channelMp3(), 'More - Pastor.mp3', 1, 'abcdef0123456789abzz')->assertForbidden();

    $this->actingAs(superAdmin())->put(route('super-admin.radio.block', $station))->assertRedirect(); // and back on
    expect($station->fresh()->is_blocked)->toBeFalse();
});

test('the radio still works before the database update that adds channels', function () {
    // Without the channels table the Gospel radio is one channel with no row: its songs are all the tracks.
    $reflection = new ReflectionProperty(RadioStation::class, 'ready');
    $reflection->setValue(null, false);
    try {
        $gospel = RadioStation::gospel();
        expect($gospel->exists)->toBeFalse()->and($gospel->isGospel())->toBeTrue();

        RadioTrack::create(['title' => 'Old song', 'file_path' => 'radio/old.mp3', 'duration_seconds' => 120, 'position' => 1]);
        $radio = app(RadioService::class);
        expect($radio->nowPlaying()['track']['title'])->toBe('Old song')->and($radio->stationsFor(null)->pluck('slug')->all())->toBe(['gospel']);
    } finally {
        $reflection->setValue(null, null);
    }
});

test('the channel pages show how long one round lasts, also when the page has other lists on it', function () {
    RadioTrack::create(['station_id' => RadioStation::podcast()->id, 'title' => 'Long talk', 'file_path' => 'radio/long.mp3', 'duration_seconds' => 540, 'position' => 1]);

    // The super admin page has the channel tabs (a loop of its own) above the part that shows the length.
    $this->actingAs(superAdmin())->get(route('super-admin.radio.index', ['station' => 'podcast']))->assertOk()->assertSee('9 min')->assertSee('160.0');

    [$church, $admin] = channelChurch();
    RadioTrack::create(['station_id' => RadioStation::forChurch($church)->id, 'title' => 'Church talk', 'file_path' => 'radio/c.mp3', 'duration_seconds' => 1200, 'position' => 1]);
    $this->actingAs($admin)->get(route('church-admin.radio.index', $church))->assertOk()->assertSee('20 min')->assertSee('72.0');
});

// ── The Podcast radio plays when nothing else is on ─────────────────────────────────────────────────────

test('when the Gospel radio has nothing on, the Podcast radio plays instead', function () {
    $this->actingAs(superAdmin())->postJson(route('super-admin.radio.store'), ['track' => channelFile('Hope in Hard Times - Pastor Okello.mp3'), 'station' => 'podcast'])->assertOk();
    Sanctum::actingAs(member(), ['member']);

    // The Gospel radio is empty: the old call and the new one both answer with the Podcast radio, and say why.
    foreach ([route('api.v1.radio'), route('api.v1.radio.station', 'gospel')] as $url) {
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.fallback', true)->assertJsonPath('data.fallback_for.slug', 'gospel')->assertJsonPath('data.fallback_for.name', 'FaithFellows Radio')
            ->assertJsonPath('data.slug', 'podcast')->assertJsonPath('data.enabled', true)->assertJsonPath('data.now.track.title', 'Hope in Hard Times');
    }

    // Once a song is added, the Gospel radio plays again and there is no fallback.
    $this->actingAs(superAdmin())->postJson(route('super-admin.radio.store'), ['track' => channelFile('Amazing Grace - Test Choir.mp3')])->assertOk();
    $this->getJson(route('api.v1.radio'))->assertJsonPath('data.fallback', false)->assertJsonPath('data.slug', 'gospel')->assertJsonPath('data.now.track.title', 'Amazing Grace');

    // A switched-off Gospel radio falls back too.
    app(RadioService::class)->saveSettings('FaithFellows Radio', false);
    $this->getJson(route('api.v1.radio'))->assertJsonPath('data.fallback', true)->assertJsonPath('data.now.track.title', 'Hope in Hard Times');
});

test('an empty church channel falls back to the Podcast radio for its followers only', function () {
    [$church, , $follower] = channelChurch();
    $this->actingAs(superAdmin())->postJson(route('super-admin.radio.store'), ['track' => channelFile('Hope in Hard Times - Pastor Okello.mp3'), 'station' => 'podcast'])->assertOk();
    $station = RadioStation::forChurch($church);

    Sanctum::actingAs($follower, ['member']);
    $this->getJson(route('api.v1.radio.station', $station->slug))->assertOk()
        ->assertJsonPath('data.fallback', true)->assertJsonPath('data.fallback_for.slug', $station->slug)->assertJsonPath('data.now.track.title', 'Hope in Hard Times');

    // Someone who does not follow the church still cannot ask for its channel.
    Sanctum::actingAs(member(), ['member']);
    $this->getJson(route('api.v1.radio.station', $station->slug))->assertNotFound();
});

test('with nothing on the Podcast radio either, the radio is simply quiet', function () {
    Sanctum::actingAs(member(), ['member']);

    $this->getJson(route('api.v1.radio'))->assertOk()->assertJsonPath('data.fallback', false)->assertJsonPath('data.enabled', false)->assertJsonPath('data.now', null);
    expect(app(RadioService::class)->stationsFor(null))->toHaveCount(0);
});

test('the Podcast radio page tells the admin about the fallback', function () {
    $this->actingAs(superAdmin())->get(route('super-admin.radio.index', ['station' => 'podcast']))->assertOk()->assertSee('also plays whenever the Gospel radio or a church channel has nothing on');
    $this->actingAs(superAdmin())->get(route('super-admin.radio.index'))->assertOk()->assertDontSee('also plays whenever the Gospel radio');
});
