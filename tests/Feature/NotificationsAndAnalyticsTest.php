<?php

use App\Models\BlogPost;
use App\Models\Church;
use App\Models\ChurchBroadcast;
use App\Models\ContentReport;
use App\Models\NotificationDelivery;
use App\Models\PlatformSetting;
use App\Models\PushSubscription;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\Care\CommunityUpdate;
use App\Notifications\Church\ChurchMessage;
use App\Notifications\Member\DailyDigest;
use App\Notifications\Support\SupportTicketUpdated;
use App\Services\ModerationService;
use App\Services\Notifications\BroadcastService;
use App\Services\Notifications\DigestService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Cache::forget('platform_settings');
    PaymentService::flush();
    config(['services.webpush.public_key' => 'BTest', 'services.webpush.private_key' => 'test']);
});

/** @return array{0: Church, 1: User, 2: User} church, admin, member */
function messagingChurch(array $memberAttributes = []): array
{
    $admin = member();
    $church = Church::factory()->create();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);
    $member = member(['phone' => '0772123456', ...$memberAttributes]);
    $church->subscriptions()->create(['user_id' => $member->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);

    return [$church, $admin, $member];
}

function message(Church $church, array $channels = ['mail', 'webpush'], bool $urgent = false, string $category = 'announcement'): ChurchMessage
{
    $n = new ChurchMessage('Service moved', 'Join us at 9 AM.', 'https://example.test', $church->name, $category, $channels, $urgent);
    $n->churchId = $church->id;

    return $n;
}

/*
|--------------------------------------------------------------------------
| Channel selection: preferences, fallback, digest, quiet hours
|--------------------------------------------------------------------------
*/

test('channels follow preferences and include push only when the member has a device', function () {
    [$church, , $member] = messagingChurch();
    $service = app(NotificationService::class);

    // Announcement defaults: in-app + push + email. No push device yet → in-app + email.
    expect($service->channelsFor($member, message($church)))->toEqualCanonicalizing(['database', 'mail']);

    PushSubscription::create(['user_id' => $member->id, 'endpoint' => 'https://push.example/1', 'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example/1'), 'public_key' => 'k', 'auth_token' => 'a']);
    expect(app(NotificationService::class)->channelsFor($member->fresh(), message($church)))->toEqualCanonicalizing(['database', 'mail', 'webpush']);
});

test('fallback chain kicks in only when no wanted channel can reach the member, and never to paid SMS unless urgent', function () {
    [$church, , $member] = messagingChurch(['email_verified_at' => null]);
    $service = app(NotificationService::class);

    // Wants push + email; no device and unverified email → nothing reachable → no paid fallback for routine messages.
    expect($service->channelsFor($member, message($church)))->toBe(['database']);
    // Urgent → falls back to SMS.
    expect($service->channelsFor($member, message($church, ['mail', 'webpush'], true)))->toContain('sms');
});

test('digest mode keeps routine alerts in-app; urgent and live alerts still go out', function () {
    [$church, , $member] = messagingChurch(['digest_mode' => true]);
    $service = app(NotificationService::class);

    expect($service->channelsFor($member, message($church)))->toBe(['database'])
        ->and($service->channelsFor($member, message($church, ['mail', 'webpush'], false, 'live')))->toContain('mail')
        ->and($service->channelsFor($member, message($church, ['sms'], true)))->toContain('sms');
});

test('quiet hours deliver in-app now and hold push/SMS until the window ends', function () {
    Queue::fake();
    Notification::fake();
    [$church, , $member] = messagingChurch(['quiet_hours_start' => '00:00', 'quiet_hours_end' => '23:59', 'timezone' => 'Africa/Kampala']);
    $notification = message($church, ['sms'], false);
    $notification->forceChannels = ['sms'];

    app(NotificationService::class)->deliver($member, $notification);

    Notification::assertSentTo($member, ChurchMessage::class, fn ($n, $channels) => ! in_array('sms', $channels, true));
    Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->channels === ['sms'] && $job->delay !== null);
});

test('SMS goes through Africa’s Talking and the delivery is logged with its cost', function () {
    config(['services.africastalking.sms_enabled' => true, 'services.africastalking.api_key' => 'key', 'services.africastalking.username' => 'faithfellows']);
    Http::fake(['api.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => [['statusCode' => 101, 'messageId' => 'ATX-1', 'cost' => 'UGX 35.0000', 'status' => 'Success']]]])]);
    [$church, , $member] = messagingChurch();

    app(NotificationService::class)->deliver($member, message($church, ['sms'], true));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'messaging') && $request['to'] === '+256772123456');
    $delivery = NotificationDelivery::where('channel', 'sms')->sole();
    expect($delivery->status)->toBe('sent')->and((float) $delivery->cost)->toBe(35.0)->and($delivery->provider_ref)->toBe('ATX-1');
    expect(NotificationDelivery::where('channel', 'mail')->exists())->toBeTrue();
});

test('members can register and remove a push subscription', function () {
    [, , $member] = messagingChurch();
    $payload = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'BNc', 'auth' => 'tBH']];

    $this->actingAs($member)->postJson(route('push-subscriptions.store'), $payload)->assertCreated();
    $this->actingAs($member)->postJson(route('push-subscriptions.store'), $payload)->assertCreated();
    expect(PushSubscription::where('user_id', $member->id)->count())->toBe(1);

    $this->actingAs($member)->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => $payload['endpoint']])->assertOk();
    expect(PushSubscription::count())->toBe(0);
});

test('the daily digest summarises unread notifications once', function () {
    Notification::fake();
    [$church, , $member] = messagingChurch(['digest_mode' => true]);
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(), 'type' => ChurchMessage::class, 'notifiable_type' => User::class, 'notifiable_id' => $member->id,
        'church_id' => $church->id, 'category' => 'announcement', 'data' => json_encode(['title' => 'Choir practice moved', 'church' => $church->name]),
        'created_at' => now()->subHour(), 'updated_at' => now()->subHour(),
    ]);

    expect(app(DigestService::class)->sendDue())->toBe(1);
    Notification::assertSentTo($member, DailyDigest::class, fn ($n) => $n->total === 1 && $n->items->first()['title'] === 'Choir practice moved');
    expect(app(DigestService::class)->sendDue())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Notification composer
|--------------------------------------------------------------------------
*/

test('church admins send a message to a stage segment; other members do not get it', function () {
    Notification::fake();
    [$church, $admin, $member] = messagingChurch();
    $visitor = member();
    $church->subscriptions()->create(['user_id' => $visitor->id, 'status' => 'active', 'stage' => 'visitor', 'subscribed_at' => now()]);

    $this->actingAs($admin)->getJson(route('church-admin.notifications.estimate', [$church, 'audience' => 'stage', 'stages' => ['member']]))
        ->assertOk()->assertJson(['recipients' => 1, 'with_phone' => 1]);

    $this->actingAs($admin)->post(route('church-admin.notifications.store', $church), [
        'title' => 'Members meeting', 'body' => 'Annual general meeting after service.', 'category' => 'announcement',
        'audience' => 'stage', 'stages' => ['member'], 'channels' => ['mail'], 'send' => 'now',
    ])->assertRedirect();

    $broadcast = ChurchBroadcast::sole();
    expect($broadcast->status)->toBe('sent')->and($broadcast->recipients_count)->toBe(1);
    Notification::assertSentTo($member, ChurchMessage::class, fn ($n) => $n->broadcastId === $broadcast->id);
    Notification::assertNotSentTo($visitor, ChurchMessage::class);
});

test('scheduled messages are sent once by the scheduler and can be cancelled before', function () {
    Notification::fake();
    [$church, $admin, $member] = messagingChurch();

    $this->actingAs($admin)->post(route('church-admin.notifications.store', $church), [
        'title' => 'Fasting week', 'body' => 'Starts Monday.', 'category' => 'announcement', 'audience' => 'members',
        'send' => 'later', 'scheduled_at' => now()->addHour()->format('Y-m-d H:i'),
    ])->assertRedirect();
    $broadcast = ChurchBroadcast::sole();
    expect($broadcast->status)->toBe('scheduled')->and(app(BroadcastService::class)->sendDue())->toBe(0);

    $this->travel(2)->hours();
    expect(app(BroadcastService::class)->sendDue())->toBe(1)->and(app(BroadcastService::class)->sendDue())->toBe(0);
    Notification::assertSentToTimes($member, ChurchMessage::class, 1);
});

test('staff without the notifications permission cannot use the composer', function () {
    [$church] = messagingChurch();
    $editor = member();
    $role = $church->roles()->create(['name' => 'Editor', 'slug' => 'editor', 'permissions' => ['content.manage']]);
    $church->staffMembers()->create(['user_id' => $editor->id, 'church_role_id' => $role->id, 'status' => 'active']);

    $this->actingAs($editor)->get(route('church-admin.notifications.create', $church))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Analytics
|--------------------------------------------------------------------------
*/

test('church analytics render with exports, and giving data needs the giving permission', function () {
    [$church, $admin] = messagingChurch();
    $analyst = member();
    $role = $church->roles()->create(['name' => 'Analyst', 'slug' => 'analyst', 'permissions' => ['analytics.view']]);
    $church->staffMembers()->create(['user_id' => $analyst->id, 'church_role_id' => $role->id, 'status' => 'active']);

    $this->actingAs($admin)->get(route('church-admin.analytics', $church))->assertOk()->assertSee(__('How people give'));
    $this->actingAs($admin)->get(route('church-admin.analytics.pdf', $church))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($admin)->get(route('church-admin.analytics.export', [$church, 'attendance']))->assertOk();

    $this->actingAs($analyst)->get(route('church-admin.analytics', $church))->assertOk()->assertDontSee(__('How people give'));
    $this->actingAs($analyst)->get(route('church-admin.analytics.export', [$church, 'giving']))->assertForbidden();
});

test('super admin dashboard and analytics show platform numbers', function () {
    $admin = superAdmin();
    messagingChurch();

    $this->actingAs($admin)->get(route('super-admin.dashboard'))->assertOk()->assertSee(__('Subscription revenue (MRR)'));
    $this->actingAs($admin)->get(route('super-admin.analytics', ['days' => 30]))->assertOk();
    $this->actingAs($admin)->get(route('super-admin.analytics.export'))->assertOk();
    $this->actingAs(member())->get(route('super-admin.analytics'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Support tickets
|--------------------------------------------------------------------------
*/

test('support tickets flow between the church and Kakebe support with internal notes kept private', function () {
    Notification::fake();
    [$church, $admin] = messagingChurch();
    $agent = superAdmin();

    $this->actingAs($admin)->post(route('church-admin.support.store', $church), [
        'subject' => 'Gifts pending', 'category' => 'payments', 'priority' => 'high', 'body' => 'Airtel gifts are stuck.',
    ])->assertRedirect();
    $ticket = SupportTicket::sole();
    expect($ticket->reference)->toBe('FF-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT));
    Notification::assertSentTo($agent, SupportTicketUpdated::class);

    $this->actingAs($agent)->post(route('super-admin.support.reply', $ticket), ['body' => 'Checked logs: callback delayed.', 'is_internal' => 1])->assertRedirect();
    $this->actingAs($agent)->post(route('super-admin.support.reply', $ticket), ['body' => 'Fixed — please confirm.'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe('pending');
    Notification::assertSentTo($admin, SupportTicketUpdated::class, fn ($n) => $n->event === 'reply');

    $this->actingAs($admin)->get(route('church-admin.support.show', [$church, $ticket]))->assertOk()
        ->assertSee('Fixed — please confirm.')->assertDontSee('callback delayed');

    $this->actingAs($admin)->post(route('church-admin.support.reply', [$church, $ticket]), ['body' => 'Confirmed, thanks!'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe('open');

    // Another church cannot open this ticket through its own URL.
    [$other, $otherAdmin] = messagingChurch();
    $this->actingAs($otherAdmin)->get(route('church-admin.support.show', [$other, $ticket]))->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Moderation
|--------------------------------------------------------------------------
*/

test('members report content; church moderators hide it; super admins can suspend the author', function () {
    Notification::fake();
    [$church, $admin, $member] = messagingChurch();
    $troll = member();
    $church->subscriptions()->create(['user_id' => $troll->id, 'status' => 'active', 'subscribed_at' => now()]);
    $post = $church->posts()->create(['body' => 'Hello', 'visibility' => 'members', 'status' => 'published', 'published_at' => now()]);
    $comment = $post->comments()->create(['church_id' => $church->id, 'user_id' => $troll->id, 'body' => 'Buy cheap phones!!!']);

    $this->actingAs($troll)->post(route('reports.store', ['comment', $comment->id]), ['reason' => 'spam'])->assertStatus(422);
    $this->actingAs($member)->post(route('reports.store', ['comment', $comment->id]), ['reason' => 'spam'])->assertRedirect();
    $this->actingAs(member())->post(route('reports.store', ['comment', $comment->id]), ['reason' => 'spam'])->assertForbidden();

    expect($comment->fresh()->status)->toBe('flagged');
    $report = ContentReport::sole();
    Notification::assertSentTo($admin, CommunityUpdate::class);

    $this->actingAs($admin)->get(route('church-admin.reports.index', $church))->assertOk()->assertSee('Buy cheap phones');
    $this->actingAs($admin)->put(route('church-admin.reports.update', [$church, $report]), ['action' => 'hide'])->assertRedirect();
    expect($comment->fresh()->status)->toBe('hidden')->and($report->fresh()->status)->toBe('actioned');

    $second = $post->comments()->create(['church_id' => $church->id, 'user_id' => $troll->id, 'body' => 'More spam']);
    $report2 = app(ModerationService::class)->report($member, $second, ['reason' => 'spam']);
    $this->actingAs(superAdmin())->patch(route('super-admin.moderation.update', $report2), ['action' => 'suspend'])->assertRedirect();
    expect($troll->fresh()->status)->toBe('suspended')->and($second->fresh()->status)->toBe('hidden');
});

/*
|--------------------------------------------------------------------------
| Super admin platform controls
|--------------------------------------------------------------------------
*/

test('switching a gateway off removes it from giving and rejects new gifts with it', function () {
    [$church, , $member] = messagingChurch();
    foreach (['mtn_momo', 'airtel_money'] as $type) {
        $church->givingChannels()->create(['type' => $type, 'is_active' => true]);
    }

    $this->actingAs(superAdmin())->put(route('super-admin.settings.gateways.toggle', 'airtel_money'), ['enabled' => 0])->assertRedirect();
    expect(PaymentService::enabled('airtel_money'))->toBeFalse()->and(PaymentService::enabled('mtn_momo'))->toBeTrue();

    $this->actingAs($member)->post(route('give.store', $church), ['amount' => 5000, 'category' => 'offering', 'method' => 'airtel_money', 'phone' => '0752123456'])
        ->assertSessionHasErrors('method');
});

test('suspending a user signs them out everywhere; settings can pause church applications', function () {
    $admin = superAdmin();
    $user = member();
    DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($admin)->patch(route('super-admin.users.update', $user), ['action' => 'suspend'])->assertRedirect();
    expect($user->fresh()->status)->toBe('suspended')->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();

    $this->actingAs($admin)->put(route('super-admin.settings.update'), ['registrations_open' => 0, 'trial_months' => 6])->assertRedirect();
    expect(PlatformSetting::get('registrations_open'))->toBe('0');
    $this->actingAs(member())->get(route('church.apply'))->assertOk()->assertSee(__('Applications are paused'));
});

test('super admin pages render', function () {
    $admin = superAdmin();
    [$church] = messagingChurch();
    $post = BlogPost::create(['title' => 'Hello', 'slug' => 'hello', 'body' => '<p>Hi</p>', 'status' => 'published', 'published_at' => now()]);

    foreach (['users.index', 'support.index', 'moderation.index', 'blog.index', 'blog.create', 'messages.index', 'audit-logs', 'settings', 'settings.gateways'] as $route) {
        $this->actingAs($admin)->get(route('super-admin.'.$route))->assertOk();
    }
    $this->actingAs($admin)->get(route('super-admin.blog.edit', $post))->assertOk();
});

test('USSD callbacks need the shared secret when one is configured', function () {
    config(['services.africastalking.ussd_secret' => 's3cret']);

    $this->post(route('webhooks.ussd'), ['text' => '', 'phoneNumber' => '+256772123456'])->assertForbidden();
    $this->post(route('webhooks.ussd').'?secret=s3cret', ['text' => '', 'phoneNumber' => '+256772123456'])->assertOk()->assertSee('CON Welcome');
});
