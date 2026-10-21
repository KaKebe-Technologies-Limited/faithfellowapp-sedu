<?php

use App\Models\Church;
use App\Models\Connection;
use App\Models\DeviceToken;
use App\Models\DirectMessage;
use App\Models\User;
use App\Notifications\Church\ChurchMessage;
use App\Services\NotificationService;
use App\Services\PeopleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/*
| Push notifications to the phone app: registering a phone, the Expo push channel for church news, pushes for
| connections and chat, the "send me a test" button and the notification settings screen.
*/

const PUSH_TOKEN = 'ExponentPushToken[aaaaaaaaaaaaaaaaaaaaaa]';

function pushChurch(): Church
{
    return Church::factory()->create();
}

function pushMember(Church $church, array $attributes = []): User
{
    $user = member(array_merge(['date_of_birth' => now()->subYears(30)->toDateString(), 'directory_opt_in' => true], $attributes));
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);

    return $user;
}

function withPhone(User $user, string $token = PUSH_TOKEN): DeviceToken
{
    return DeviceToken::create(['user_id' => $user->id, 'token' => $token, 'platform' => 'android', 'last_used_at' => now()]);
}

function pushActing(User $user): User
{
    Sanctum::actingAs($user, ['member']);

    return $user;
}

function expoOk(int $count = 1): void
{
    Http::fake(['exp.host/*' => Http::response(['data' => array_fill(0, $count, ['status' => 'ok', 'id' => 'ticket-1'])])]);
}

// ── Registering a phone ─────────────────────────────────────────────────────────────────────────────────

test('a member registers their phone, and signing in as someone else on it moves the token', function () {
    $church = pushChurch();
    $ann = pushMember($church);
    $bob = pushMember($church);

    pushActing($ann);
    $this->postJson(route('api.v1.devices.store'), ['token' => PUSH_TOKEN, 'platform' => 'android', 'name' => 'Tecno Spark'])->assertCreated()->assertJson(['registered' => true]);
    $this->postJson(route('api.v1.devices.store'), ['token' => PUSH_TOKEN, 'platform' => 'android'])->assertOk();
    expect(DeviceToken::count())->toBe(1)->and($ann->deviceTokens()->count())->toBe(1);

    pushActing($bob);
    $this->postJson(route('api.v1.devices.store'), ['token' => PUSH_TOKEN, 'platform' => 'android'])->assertOk();
    expect($ann->deviceTokens()->count())->toBe(0)->and($bob->deviceTokens()->count())->toBe(1);
});

test('only real Expo tokens are accepted and signing out removes the phone', function () {
    $user = pushActing(pushMember(pushChurch()));

    $this->postJson(route('api.v1.devices.store'), ['token' => 'not-a-token'])->assertUnprocessable()->assertJsonValidationErrors('token');
    $this->postJson(route('api.v1.devices.store'), ['token' => PUSH_TOKEN, 'platform' => 'windows'])->assertUnprocessable()->assertJsonValidationErrors('platform');

    withPhone($user);
    $this->deleteJson(route('api.v1.devices.destroy'), ['token' => PUSH_TOKEN])->assertOk()->assertJson(['registered' => false]);
    expect($user->deviceTokens()->count())->toBe(0);
});

test('a member keeps at most eight phones, newest first', function () {
    $user = pushActing(pushMember(pushChurch()));

    foreach (range(1, 10) as $i) {
        $this->postJson(route('api.v1.devices.store'), ['token' => sprintf('ExponentPushToken[device%02dxxxxxxxxxxxx]', $i)])->assertSuccessful();
    }

    expect($user->deviceTokens()->count())->toBe(8)
        ->and($user->deviceTokens()->where('token', 'ExponentPushToken[device01xxxxxxxxxxxx]')->exists())->toBeFalse();
});

test('the test button reaches the phone and explains itself when there is none', function () {
    $user = pushActing(pushMember(pushChurch()));

    $this->postJson(route('api.v1.devices.test'))->assertUnprocessable();

    withPhone($user);
    expoOk();
    $this->postJson(route('api.v1.devices.test'))->assertOk()->assertJson(['devices' => 1, 'sent' => 1]);
    Http::assertSent(fn ($request) => $request->url() === 'https://exp.host/--/api/v2/push/send'
        && $request[0]['to'] === PUSH_TOKEN && $request[0]['title'] === 'Notifications are working' && $request[0]['data']['kind'] === 'test');
});

// ── Church news ─────────────────────────────────────────────────────────────────────────────────────────

function churchNews(Church $church, string $category = 'announcement', bool $urgent = false): ChurchMessage
{
    $n = new ChurchMessage('Service moved', 'Join us at 9 AM.', 'https://example.test/announcements/7', $church->name, $category, ['webpush', 'mail'], $urgent);
    $n->churchId = $church->id;

    return $n;
}

test('members with the app get church news by push, others do not', function () {
    $church = pushChurch();
    $withApp = pushMember($church);
    $without = pushMember($church);
    withPhone($withApp);
    $service = app(NotificationService::class);

    expect($service->channelsFor($withApp, churchNews($church)))->toContain('expo')->toContain('database')
        ->and($service->channelsFor($without, churchNews($church)))->not->toContain('expo');
});

test('a member who turned push off for a kind of news does not get it on the phone', function () {
    $church = pushChurch();
    $member = pushMember($church);
    withPhone($member);
    pushActing($member);

    $this->putJson(route('api.v1.notification-settings.update'), ['categories' => ['announcement' => ['push' => false]]])->assertOk();

    $channels = app(NotificationService::class)->channelsFor($member->fresh(), churchNews($church));
    expect($channels)->not->toContain('expo')->not->toContain('webpush')->toContain('database');
});

test('church news is sent to Expo with where to open it, and a phone that is gone is forgotten', function () {
    $church = pushChurch();
    $member = pushMember($church);
    withPhone($member);
    withPhone($member, 'ExponentPushToken[bbbbbbbbbbbbbbbbbbbbbb]');
    Http::fake(['exp.host/*' => Http::response(['data' => [
        ['status' => 'ok', 'id' => 'ticket-1'],
        ['status' => 'error', 'message' => 'not registered', 'details' => ['error' => 'DeviceNotRegistered']],
    ]])]);

    app(NotificationService::class)->deliver($member, churchNews($church));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/push/send')
        && $request[0]['title'] === 'Service moved'
        && $request[0]['body'] === 'Join us at 9 AM.'
        && $request[0]['data']['url'] === 'https://example.test/announcements/7'
        && $request[0]['channelId'] === 'default');
    expect($member->deviceTokens()->pluck('token')->all())->toBe([PUSH_TOKEN]);
});

test('church news goes only to the phones of people who follow that church', function () {
    $grace = pushChurch();
    $other = pushChurch();
    $follower = pushMember($grace);
    $ofOther = pushMember($other);
    $left = pushMember($grace);
    $grace->subscriptions()->where('user_id', $left->id)->update(['status' => 'left']);
    withPhone($follower, 'ExponentPushToken[followerfollowerfollow]');
    withPhone($ofOther, 'ExponentPushToken[otherchurchotherchurch]');
    withPhone($left, 'ExponentPushToken[leftleftleftleftleftle]');
    Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]])]);

    $recipients = app(NotificationService::class)->recipients($grace->id, 'members')->pluck('id')->all();
    expect($recipients)->toContain($follower->id)->not->toContain($ofOther->id)->not->toContain($left->id);

    app(NotificationService::class)->notifyChurch($grace, churchNews($grace));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/push/send') && $request[0]['to'] === 'ExponentPushToken[followerfollowerfollow]');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/push/send')
        && in_array($request[0]['to'] ?? '', ['ExponentPushToken[otherchurchotherchurch]', 'ExponentPushToken[leftleftleftleftleftle]'], true));
});

test('urgent news uses the urgent channel and live services the live channel', function () {
    $church = pushChurch();
    $member = pushMember($church);
    withPhone($member);
    expoOk();

    app(NotificationService::class)->deliver($member, churchNews($church, 'announcement', true));
    Http::assertSent(fn ($request) => $request[0]['channelId'] === 'urgent');

    Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-2']]])]);
    $live = new ChurchMessage('We are live', 'Join now', 'https://example.test/live/1', $church->name, 'live', ['webpush']);
    $live->churchId = $church->id;
    app(NotificationService::class)->deliver($member, $live);
    Http::assertSent(fn ($request) => $request[0]['channelId'] === 'live');
});

// ── Connections and chat ────────────────────────────────────────────────────────────────────────────────

test('a connection request and its acceptance are pushed to the phone', function () {
    $church = pushChurch();
    $ann = pushMember($church, ['name' => 'Ann Akello']);
    $bob = pushMember($church, ['name' => 'Bob Okello']);
    withPhone($bob);
    expoOk();

    $connection = app(PeopleService::class)->request($ann, $bob, 'Let us connect');
    Http::assertSent(fn ($r) => $r[0]['title'] === 'Ann Akello wants to connect with you' && $r[0]['data']['kind'] === 'connection_request' && $r[0]['data']['ref'] === $ann->id);

    withPhone($ann, 'ExponentPushToken[cccccccccccccccccccccc]');
    Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok', 'id' => 't3']]])]);
    app(PeopleService::class)->respond($bob, $connection, true);
    Http::assertSent(fn ($r) => $r[0]['to'] === 'ExponentPushToken[cccccccccccccccccccccc]' && $r[0]['data']['kind'] === 'connection_accepted');
});

test('a chat message pops up on the other phone, a burst within seconds makes one buzz, and never in quiet hours', function () {
    Cache::flush();
    $church = pushChurch();
    $ann = pushMember($church, ['name' => 'Ann Akello']);
    $bob = pushMember($church, ['name' => 'Bob Okello']);
    Connection::create(['requester_id' => $ann->id, 'addressee_id' => $bob->id, 'status' => 'accepted', 'responded_at' => now()]);
    withPhone($bob);
    expoOk();
    pushActing($ann);

    $this->postJson(route('api.v1.messages.send', $bob), ['body' => 'Peace be with you'])->assertCreated();
    DirectMessage::create(['sender_id' => $bob->id, 'recipient_id' => $ann->id, 'body' => 'And with you']); // Bob answers, so the chat is open
    $this->postJson(route('api.v1.messages.send', $bob), ['body' => 'Are you there?'])->assertCreated();

    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => $r[0]['title'] === 'Ann Akello' && $r[0]['body'] === 'Peace be with you' && $r[0]['channelId'] === 'messages' && $r[0]['data']['ref'] === $ann->id);
    expect(DirectMessage::count())->toBe(3);

    // In quiet hours the message is kept, the buzz is not.
    Cache::flush();
    Http::fake();
    $bob->forceFill(['quiet_hours_start' => '00:00', 'quiet_hours_end' => '23:59', 'timezone' => config('app.timezone')])->save();
    $this->postJson(route('api.v1.messages.send', $bob), ['body' => 'Good night'])->assertCreated();
    Http::assertNothingSent();
});

test('people who muted "People & messages" are not pushed', function () {
    Cache::flush();
    $church = pushChurch();
    $ann = pushMember($church);
    $bob = pushMember($church);
    Connection::create(['requester_id' => $ann->id, 'addressee_id' => $bob->id, 'status' => 'accepted', 'responded_at' => now()]);
    withPhone($bob);
    Http::fake();

    pushActing($bob);
    $this->putJson(route('api.v1.notification-settings.update'), ['categories' => ['social' => ['push' => false]]])->assertOk();

    pushActing($ann);
    $this->postJson(route('api.v1.messages.send', $bob), ['body' => 'Hello'])->assertCreated();
    Http::assertNothingSent();
});

// ── Settings ────────────────────────────────────────────────────────────────────────────────────────────

test('notification settings can be read and changed from the app', function () {
    $church = pushChurch();
    $member = pushActing(pushMember($church));

    $before = $this->getJson(route('api.v1.notification-settings.show'))->assertOk()->json('data');
    $announcement = collect($before['categories'])->firstWhere('key', 'announcement');
    expect($announcement['push'])->toBeTrue()->and($announcement['email'])->toBeTrue()
        ->and(collect($before['categories'])->pluck('key')->all())->toContain('social', 'live', 'games')
        ->and($before['quiet_hours'])->toBeNull()->and($before['digest_mode'])->toBeFalse()
        ->and($before['churches'][0]['muted'])->toBeFalse();

    $after = $this->putJson(route('api.v1.notification-settings.update'), [
        'categories' => ['announcement' => ['push' => true, 'email' => false], 'prayer' => ['push' => false]],
        'quiet_hours' => ['start' => '22:00', 'end' => '06:00'],
        'digest_mode' => true,
    ])->assertOk()->json('data');

    $categories = collect($after['categories'])->keyBy('key');
    expect($categories['announcement']['push'])->toBeTrue()->and($categories['announcement']['email'])->toBeFalse()
        ->and($categories['prayer']['push'])->toBeFalse()
        ->and($categories['live']['push'])->toBeTrue()
        ->and($after['quiet_hours'])->toBe(['start' => '22:00', 'end' => '06:00'])->and($after['digest_mode'])->toBeTrue();

    expect($member->fresh()->quiet_hours_start)->toStartWith('22:00');

    $this->putJson(route('api.v1.notification-settings.update'), ['quiet_hours' => null])->assertOk()->assertJsonPath('data.quiet_hours', null);
});

test('muting a church from the app silences it and unmuting restores it', function () {
    $church = pushChurch();
    $member = pushActing(pushMember($church));
    $service = app(NotificationService::class);

    $this->putJson(route('api.v1.notification-settings.update'), ['muted_churches' => [$church->id]])->assertOk()->assertJsonPath('data.churches.0.muted', true);
    expect($service->channelsFor($member->fresh(), churchNews($church)))->toBe([]);

    $this->putJson(route('api.v1.notification-settings.update'), ['muted_churches' => []])->assertOk()->assertJsonPath('data.churches.0.muted', false);
    expect(app(NotificationService::class)->channelsFor($member->fresh(), churchNews($church)))->toContain('database');
});

test('quiet hours must be real times', function () {
    pushActing(pushMember(pushChurch()));

    $this->putJson(route('api.v1.notification-settings.update'), ['quiet_hours' => ['start' => '25:00', 'end' => 'soon']])
        ->assertUnprocessable()->assertJsonValidationErrors(['quiet_hours.start', 'quiet_hours.end']);
});

test('push endpoints need a signed-in member', function () {
    $this->postJson(route('api.v1.devices.store'), ['token' => PUSH_TOKEN])->assertUnauthorized();
    $this->getJson(route('api.v1.notification-settings.show'))->assertUnauthorized();
});
