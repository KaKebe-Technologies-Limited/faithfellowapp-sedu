<?php

use App\Jobs\SendPushBatch;
use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use App\Models\Podcast;
use App\Models\PodcastEpisode;
use App\Models\RadioStation;
use App\Models\RadioTrack;
use App\Models\User;
use App\Services\ContentPushes;
use App\Services\PodcastService;
use App\Services\PushBroadcaster;
use App\Services\VerseBroadcaster;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| Pop-up notifications for new things: the verse of the day each morning, a new podcast, a new episode, a new teaching on the Podcast radio.
| They go to phones that have the app, only for people who have that kind of news on, and right after the request that caused them.
*/

function newsPhone(?User $user = null): User
{
    $user ??= member();
    DeviceToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[abcdefghij'.$user->id.']', 'platform' => 'android', 'last_used_at' => now()]);

    return $user;
}

function newsPodcast(User $owner, string $status = 'approved', string $name = 'Morning Manna'): Podcast
{
    return Podcast::create([
        'user_id' => $owner->id, 'name' => $name, 'host_name' => 'Pastor Ruth', 'summary' => 'A short word from the Bible each day.',
        'category' => 'devotional', 'language' => 'en', 'status' => $status, 'approved_at' => $status === 'approved' ? now() : null,
    ]);
}

function newsEpisode(Podcast $podcast, array $overrides = []): PodcastEpisode
{
    return $podcast->episodes()->create(array_merge(['title' => 'Walking by faith', 'audio_url' => 'https://audio.example.com/walking.mp3', 'duration_seconds' => 1450, 'status' => 'published', 'published_at' => now()->subHour()], $overrides));
}

function bibleOk(): void
{
    // Any chapter: forty verses, so whichever reference is picked for the day has words.
    $verses = collect(range(1, 40))->map(fn ($n) => ['verse' => $n, 'text' => "Verse {$n}:\n a few words of scripture."])->all();
    Http::fake(['bible-api.com/*' => Http::response(['verses' => $verses])]);
}

// ── The verse of the day ────────────────────────────────────────────────────────────────────────────────

test('the verse of the day is pushed once each morning, with the reference and the words', function () {
    Bus::fake();
    bibleOk();
    newsPhone();
    newsPhone();
    $verse = app(VerseBroadcaster::class);

    $this->travelTo(today()->setTime(5, 59));
    expect($verse->sendDue())->toBeFalse();

    $this->travelTo(today()->setTime(6, 1));
    expect($verse->sendDue())->toBeTrue();
    Bus::assertDispatchedAfterResponse(SendPushBatch::class, fn ($job) => $job->category === 'verse' && count($job->userIds) === 2
        && $job->message['title'] === '📖 Verse of the day' && str_contains($job->message['body'], 'a few words of scripture')
        && $job->message['data']['kind'] === 'verse' && isset($job->message['data']['book'], $job->message['data']['chapter']));

    // The server looks every minute, but the verse does not go twice in a day.
    $this->travelTo(today()->setTime(6, 40));
    expect($verse->sendDue())->toBeFalse();

    // The next morning it goes again; a server that was down until late in the morning skips that day instead of sending late.
    $this->travelTo(today()->addDay()->setTime(6, 5));
    expect($verse->sendDue())->toBeTrue();
    $this->travelTo(today()->addDays(2)->setTime(11, 0));
    expect($verse->sendDue())->toBeFalse();
});

test('the verse of the day still goes out when the Bible cannot be reached, with the reference alone', function () {
    Http::fake(['bible-api.com/*' => Http::response('', 500)]);

    $message = app(VerseBroadcaster::class)->message(now());

    expect($message['body'])->toContain('tap to read')->and($message['data']['kind'])->toBe('verse')->and($message['data'])->toHaveKey('chapter');
});

test('the super admin sets the time of the verse, switches it off and sends it by hand', function () {
    Bus::fake();
    bibleOk();
    $admin = superAdmin();
    newsPhone();

    $this->actingAs($admin)->get(route('super-admin.inspiration.index'))->assertOk()->assertSee('Verse of the day on phones');
    $this->actingAs($admin)->put(route('super-admin.inspiration.verse'), ['auto' => 1, 'time' => '05:30'])->assertRedirect();
    expect(app(VerseBroadcaster::class)->time())->toBe('05:30')->and(app(VerseBroadcaster::class)->auto())->toBeTrue();
    $this->actingAs($admin)->put(route('super-admin.inspiration.verse'), ['time' => '5:30'])->assertSessionHasErrors('time');

    member();
    member();
    $this->actingAs($admin)->post(route('super-admin.inspiration.verse-send'))->assertRedirect()
        ->assertSessionHas('status', fn ($text) => str_contains($text, 'on its way to 1 member ') && str_contains($text, '3 other members have not turned notifications on'));
    Bus::assertDispatchedAfterResponse(SendPushBatch::class, fn ($job) => $job->category === 'verse' && count($job->userIds) === 1);

    // A double tap is not sent twice, and members cannot do this.
    Bus::fake();
    $this->actingAs($admin)->post(route('super-admin.inspiration.verse-send'))->assertRedirect();
    Bus::assertNotDispatchedAfterResponse(SendPushBatch::class);
    $this->actingAs(member())->post(route('super-admin.inspiration.verse-send'))->assertForbidden();

    // Off means off.
    $this->actingAs($admin)->put(route('super-admin.inspiration.verse'), ['time' => '06:00'])->assertRedirect();
    Cache::flush();
    $this->travelTo(today()->setTime(6, 5));
    expect(app(VerseBroadcaster::class)->sendDue())->toBeFalse();
});

test('a batch goes only to people who have that kind of news on and are not in their quiet hours', function () {
    Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]])]);
    $wants = newsPhone();
    $muted = newsPhone();
    $quiet = newsPhone();
    NotificationPreference::create(['user_id' => $muted->id, 'church_id' => null, 'category' => 'verse', 'channels' => [], 'enabled' => false]);
    $quiet->forceFill(['quiet_hours_start' => '00:00', 'quiet_hours_end' => '23:59', 'timezone' => config('app.timezone')])->save();

    $sent = app(PushBroadcaster::class)->deliver([$wants->id, $muted->id, $quiet->id], 'verse', ['title' => 'Verse', 'body' => 'Be still.', 'data' => ['kind' => 'verse']]);

    expect($sent)->toBe(1);
    Http::assertSent(fn ($request) => count($request->data()) === 1 && $request[0]['to'] === 'ExponentPushToken[abcdefghij'.$wants->id.']');
});

test('the notification settings list the new kinds so members can switch them off', function () {
    expect(config('faithfellows.notification_categories'))->toHaveKeys(['verse', 'podcast'])
        ->and(NotificationPreference::DEFAULT_CHANNELS['verse'])->toContain('webpush')
        ->and(NotificationPreference::DEFAULT_CHANNELS['podcast'])->toContain('webpush');
});

// ── Podcasts ────────────────────────────────────────────────────────────────────────────────────────────

test('a new episode of the platform\'s own podcast pops up for everyone, a member\'s podcast only for its followers', function () {
    Bus::fake();
    $admin = newsPhone(superAdmin());
    $ruth = newsPhone();
    $fan = newsPhone();
    $other = newsPhone();
    $own = newsPodcast($admin);
    $mine = newsPodcast($ruth);
    $mine->followers()->attach($fan->id);

    expect(app(ContentPushes::class)->episode(newsEpisode($own, ['title' => 'Faith that moves'])))->toBe(3);
    Bus::assertDispatchedAfterResponse(SendPushBatch::class, fn ($job) => $job->category === 'podcast' && $job->message['body'] === 'Faith that moves'
        && str_contains($job->message['title'], 'Morning Manna') && $job->message['data'] === ['kind' => 'podcast', 'ref' => $own->id, 'url' => '/podcast-studio/'.$own->id]
        && collect([$ruth->id, $fan->id, $other->id])->every(fn ($id) => in_array($id, $job->userIds)) && ! in_array($admin->id, $job->userIds));

    Bus::fake();
    expect(app(ContentPushes::class)->episode(newsEpisode($mine, ['title' => 'Rest for the weary'])))->toBe(1);
    Bus::assertDispatchedAfterResponse(SendPushBatch::class, fn ($job) => $job->userIds === [$fan->id] && $job->message['body'] === 'Rest for the weary');
});

test('an episode is news only once, and only when it is out, fresh and its podcast is approved', function () {
    Bus::fake();
    $admin = newsPhone(superAdmin());
    newsPhone();
    $podcast = newsPodcast($admin);
    $pushes = app(ContentPushes::class);

    $fresh = newsEpisode($podcast);
    expect($pushes->episode($fresh))->toBe(1)
        ->and($pushes->episode($fresh))->toBe(0)                                                               // not twice
        ->and($pushes->episode(newsEpisode($podcast, ['status' => 'draft'])))->toBe(0)
        ->and($pushes->episode(newsEpisode($podcast, ['published_at' => now()->addWeek()])))->toBe(0)         // scheduled for later
        ->and($pushes->episode(newsEpisode($podcast, ['published_at' => now()->subDays(30)])))->toBe(0)       // an old one from a feed
        ->and($pushes->episode(newsEpisode(newsPodcast($admin, 'pending', 'Waiting'))))->toBe(0);             // not approved yet
});

test('an episode a member adds on the website pops up for the followers of the podcast', function () {
    Bus::fake();
    Cache::forget('platform_settings');
    Http::fake(fn () => Http::response('', 200, ['Content-Type' => 'audio/mpeg']));
    $ruth = member();
    $fan = newsPhone();
    $podcast = newsPodcast($ruth);
    $podcast->followers()->attach($fan->id);

    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'Today', 'audio_url' => 'https://audio.example.com/t.mp3'])->assertRedirect();

    Bus::assertDispatchedAfterResponse(SendPushBatch::class, fn ($job) => $job->userIds === [$fan->id] && $job->message['body'] === 'Today');

    // A draft is not announced.
    Bus::fake();
    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'Later', 'audio_url' => 'https://audio.example.com/l.mp3', 'draft' => 1])->assertRedirect();
    Bus::assertNotDispatchedAfterResponse(SendPushBatch::class);
});

test('a podcast approved for the first time is announced to everyone but its maker, once', function () {
    Bus::fake();
    $ruth = newsPhone();
    $fan = newsPhone();
    $podcast = newsPodcast($ruth, 'pending');

    app(PodcastService::class)->approve($podcast);
    Bus::assertDispatchedAfterResponse(SendPushBatch::class, fn ($job) => $job->category === 'podcast' && $job->userIds === [$fan->id]
        && $job->message['title'] === '🎙️ A new podcast' && str_contains($job->message['body'], 'Morning Manna') && $job->message['data']['kind'] === 'podcast');

    // Hidden and approved again: not news a second time.
    Bus::fake();
    app(PodcastService::class)->hide($podcast->fresh());
    app(PodcastService::class)->approve($podcast->fresh());
    Bus::assertNotDispatchedAfterResponse(SendPushBatch::class);
});

test('a new teaching on the Podcast radio pops up once for a batch of uploads, and the Gospel radio stays quiet', function () {
    Bus::fake();
    $admin = superAdmin();
    $listener = newsPhone();
    $pushes = app(ContentPushes::class);
    $teaching = fn (array $with = []) => RadioTrack::create($with + ['title' => 'Hope in Hard Times', 'artist' => 'Pastor Okello', 'file_path' => 'radio/a.mp3', 'duration_seconds' => 600, 'position' => 1, 'uploaded_by' => $admin->id]);

    $song = $teaching(['title' => 'Amazing Grace']);   // the Gospel radio: no channel
    expect($pushes->radioTrack($song))->toBe(0);

    $first = $teaching(['station_id' => RadioStation::podcast()->id]);
    expect($pushes->radioTrack($first))->toBe(1);
    Bus::assertDispatchedAfterResponse(SendPushBatch::class, fn ($job) => $job->userIds === [$listener->id] && $job->message['title'] === '🎙️ New on the Podcast radio'
        && $job->message['body'] === 'Hope in Hard Times — Pastor Okello' && $job->message['data'] === ['kind' => 'radio', 'url' => '/radio']);

    // The second file of the same batch does not buzz again.
    expect($pushes->radioTrack($teaching(['station_id' => RadioStation::podcast()->id, 'title' => 'Another', 'position' => 2])))->toBe(0);
});
