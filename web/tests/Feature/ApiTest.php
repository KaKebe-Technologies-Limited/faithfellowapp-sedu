<?php

use App\Models\Church;
use App\Models\Donation;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Notifications\Care\CommunityUpdate;
use App\Services\PaymentService;
use App\Services\PrayerService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    PaymentService::flush();
    config(['faithfellows.payments.sandbox' => true]);
});

/** @return array{0: Church, 1: User, 2: string} church, member, bearer token */
function apiMember(): array
{
    $church = Church::factory()->create(['slug' => 'grace-api']);
    $member = member(['phone' => '0772123456']);
    $church->subscriptions()->create(['user_id' => $member->id, 'status' => 'active', 'subscribed_at' => now()]);

    return [$church, $member, $member->createToken('test', ['member'])->plainTextToken];
}

test('the API health check answers without a token', function () {
    $this->getJson('/api/ping')->assertOk()
        ->assertJson(['status' => 'ok', 'service' => 'FaithFellows API', 'version' => '1.0.0'])
        ->assertJsonStructure(['time', 'release'])
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('the health check says which update is live once a release file exists', function () {
    file_put_contents(base_path('RELEASE'), "abc1234 2026-10-14 10:00 UTC\n");

    try {
        $this->getJson('/api/ping')->assertOk()->assertJson(['release' => 'abc1234 2026-10-14 10:00 UTC']);
    } finally {
        unlink(base_path('RELEASE'));
    }
});

test('browsers get CORS headers on the API; credentials are not offered', function () {
    $this->call('OPTIONS', '/api/ping', [], [], [], ['HTTP_ORIGIN' => 'http://localhost:8081', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET'])
        ->assertNoContent()->assertHeader('Access-Control-Allow-Origin', '*')->assertHeaderMissing('Access-Control-Allow-Credentials');
});

test('members exchange credentials for a token; wrong passwords and super admins are refused', function () {
    $member = member(['email' => 'grace@example.com', 'password' => 'secret-pass-1']);

    $this->postJson(route('api.v1.auth.token'), ['email' => 'grace@example.com', 'password' => 'nope', 'device_name' => 'Phone'])
        ->assertStatus(422)->assertJsonValidationErrors('email');

    $token = $this->postJson(route('api.v1.auth.token'), ['email' => 'grace@example.com', 'password' => 'secret-pass-1', 'device_name' => 'Phone'])
        ->assertCreated()->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'email']])->json('token');

    $this->withToken($token)->getJson(route('api.v1.me'))->assertOk()->assertJsonPath('data.email', 'grace@example.com');

    $admin = superAdmin();
    $admin->update(['password' => 'admin-pass-1']);
    $this->postJson(route('api.v1.auth.token'), ['email' => $admin->email, 'password' => 'admin-pass-1', 'device_name' => 'Phone'])->assertForbidden();
});

test('protected endpoints need a token and always answer in JSON', function () {
    $this->get('/api/v1/me')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    $this->getJson('/api/v1/feed')->assertUnauthorized();
});

test('suspended members lose their token on the next request', function () {
    [, $member, $token] = apiMember();
    $member->update(['status' => 'suspended']);

    $this->withToken($token)->getJson(route('api.v1.me'))->assertForbidden();
    expect($member->tokens()->count())->toBe(0);
});

test('the church directory is public and church details include service times', function () {
    $church = Church::factory()->create(['name' => 'Victory Chapel Test', 'slug' => 'victory-test']);
    $church->serviceTimes()->create(['name' => 'Main Service', 'day_of_week' => 0, 'start_time' => '10:00']);
    Church::factory()->pending()->create(['name' => 'Hidden Pending Church']);

    $this->getJson(route('api.v1.churches.index', ['q' => 'Victory']))->assertOk()
        ->assertJsonPath('data.0.slug', 'victory-test')->assertJsonMissing(['name' => 'Hidden Pending Church']);
    $this->getJson(route('api.v1.churches.show', 'victory-test'))->assertOk()->assertJsonPath('data.service_times.0.name', 'Main Service');
});

test('members follow churches and read their feed, sermons and events through the API', function () {
    Notification::fake();
    [$church, $member, $token] = apiMember();
    $other = Church::factory()->create(['slug' => 'other-api']);
    $church->sermons()->create(['title' => 'Faith that works', 'slug' => 'faith', 'status' => 'published', 'visibility' => 'members', 'published_at' => now()->subHour()]);
    $other->sermons()->create(['title' => 'Members-only elsewhere', 'slug' => 'secret', 'status' => 'published', 'visibility' => 'members', 'published_at' => now()->subHour()]);

    $this->withToken($token)->getJson(route('api.v1.sermons.index'))->assertOk()
        ->assertJsonPath('data.0.title', 'Faith that works')->assertJsonMissing(['title' => 'Members-only elsewhere']);
    $this->withToken($token)->getJson(route('api.v1.feed'))->assertOk()->assertJsonPath('data.0.type', 'sermon');

    $this->withToken($token)->postJson(route('api.v1.churches.subscribe', 'other-api'))->assertCreated()->assertJsonPath('status', 'active');
    expect($member->fresh()->isSubscribedTo($other->id))->toBeTrue();
    $this->withToken($token)->deleteJson(route('api.v1.churches.unsubscribe', 'other-api'))->assertOk();
});

test('giving through the API starts a sandbox mobile-money payment and can be polled', function () {
    Notification::fake();
    [$church, $member, $token] = apiMember();
    $church->givingChannels()->create(['type' => 'mtn_momo', 'is_active' => true]);

    $response = $this->withToken($token)->postJson(route('api.v1.giving.store', $church->slug), [
        'amount' => 20000, 'category' => 'tithe', 'method' => 'mtn_momo', 'phone' => '0772123456',
    ])->assertCreated()->assertJsonPath('data.status', 'processing')->assertJsonPath('next.action', 'approve_on_phone');

    $reference = $response->json('data.reference');
    $this->withToken($token)->getJson(route('api.v1.giving.show', $reference))->assertOk()->assertJsonPath('data.reference', $reference);
    $this->withToken($token)->getJson(route('api.v1.giving.index'))->assertOk()->assertJsonPath('data.0.reference', $reference);

    // Another member cannot read this gift.
    $otherToken = member()->createToken('x')->plainTextToken;
    app('auth')->forgetGuards(); // Sanctum caches the first user within one test
    $this->withToken($otherToken)->getJson(route('api.v1.giving.show', $reference))->assertNotFound();
    app('auth')->forgetGuards();

    $this->withToken($token)->postJson(route('api.v1.giving.store', $church->slug), ['amount' => 100, 'method' => 'mtn_momo'])
        ->assertStatus(422)->assertJsonValidationErrors(['amount', 'phone', 'category']);
    expect(Donation::count())->toBe(1);
});

test('prayer wall works over the API and respects visibility', function () {
    Notification::fake();
    [$church, $member, $token] = apiMember();
    $friend = member();
    $church->subscriptions()->create(['user_id' => $friend->id, 'status' => 'active', 'subscribed_at' => now()]);
    $church->prayerRequests()->create(['user_id' => $friend->id, 'body' => 'Pastors only please', 'visibility' => 'pastor', 'status' => 'open']);

    $id = $this->withToken($token)->postJson(route('api.v1.prayer.store'), ['church_id' => $church->id, 'body' => 'Healing for my mother', 'visibility' => 'church'])
        ->assertCreated()->json('data.id');

    $friendToken = $friend->createToken('t')->plainTextToken;
    app('auth')->forgetGuards();
    $this->withToken($friendToken)->postJson(route('api.v1.prayer.pray', $id))->assertForbidden(); // awaiting pastor approval

    app(PrayerService::class)->approve(PrayerRequest::find($id), member());
    app('auth')->forgetGuards();
    $this->withToken($friendToken)->postJson(route('api.v1.prayer.pray', $id))->assertOk()->assertJson(['prayed' => true, 'count' => 1]);

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson(route('api.v1.prayer.index'))->assertOk()
        ->assertJsonFragment(['body' => 'Healing for my mother'])->assertJsonMissing(['body' => 'Pastors only please']);
    expect(PrayerRequest::find($id)->prayed_count)->toBe(1);
});

test('notifications can be listed and marked read', function () {
    [$church, $member, $token] = apiMember();
    $member->notify(new CommunityUpdate($church, 'Welcome!', 'https://example.test'));

    $this->withToken($token)->getJson(route('api.v1.notifications.index'))->assertOk()->assertJsonPath('meta.unread', 1)->assertJsonPath('data.0.title', 'Welcome!');
    $this->withToken($token)->postJson(route('api.v1.notifications.read'))->assertOk()->assertJson(['unread' => 0]);
});
