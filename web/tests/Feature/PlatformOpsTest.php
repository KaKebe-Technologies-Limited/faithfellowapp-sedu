<?php

use App\Jobs\SendInspirationBatch;
use App\Jobs\SendSignupAlert;
use App\Models\Church;
use App\Models\DeviceToken;
use App\Models\Inspiration;
use App\Models\NotificationPreference;
use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Notifications\Admin\NewSignup;
use App\Notifications\Billing\InvoiceIssued;
use App\Notifications\Billing\InvoicePaid;
use App\Notifications\Billing\PaymentDueSoon;
use App\Notifications\Church\ChurchBlockedForInactivity;
use App\Notifications\Church\ChurchQuiet;
use App\Services\BillingService;
use App\Services\DevicePushService;
use App\Services\DormancyService;
use App\Services\InspirationBroadcaster;
use App\Services\MailSettings;
use App\Services\Messaging\ExpoPushSender;
use App\Services\SignupAlerts;
use App\Services\SubscriberStats;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/*
| Running the platform: the monthly fee of each church (always showing the next payment day, with an email a week before and a reminder the
| day before), dormant churches (a warning, then blocking), the people who follow a church, a note to the owner for every new account,
| inspiring messages pushed to phones on a schedule or right now, and a test that shows why a push did not arrive.
*/

/** @return array{0: Church, 1: User} a church whose owner is also an admin, with the default plan */
function feeChurch(): array
{
    test()->seed(PlanSeeder::class);
    $owner = member();
    $church = Church::factory()->create(['owner_id' => $owner->id, 'status' => 'active']);
    $church->staffMembers()->create(['user_id' => $owner->id, 'is_admin' => true, 'status' => 'active']);
    app(BillingService::class)->startTrial($church);

    return [$church->fresh(), $owner];
}

// ── The monthly fee ──────────────────────────────────────────────────────────────────────────────────────────────

test('the church always sees its next payment: the day, the amount and what is left', function () {
    [$church, $owner] = feeChurch();
    $church->update(['trial_ends_at' => today()->addDays(20)]);

    $next = app(BillingService::class)->nextPayment($church->fresh());
    expect($next['state'])->toBe('free')->and($next['days'])->toBe(20)->and($next['amount'])->toBe((float) config('faithfellows.pricing.plan_price'))->and($next['invoice'])->toBeNull();

    $church->update(['trial_ends_at' => today()->addDays(3)]);
    app(BillingService::class)->generateDueInvoices();
    $next = app(BillingService::class)->nextPayment($church->fresh());
    expect($next['invoice'])->not->toBeNull()->and($next['days'])->toBe(3)->and($next['state'])->toBe('free');

    $this->actingAs($owner)->get(route('church-admin.billing.index', $church))->assertOk()
        ->assertSee('Free until')->assertSee('Pay UGX')->assertSee('mobile money')->assertDontSee('Visa');
});

test('a week before the payment day the church is emailed, then once more the day before, and thanked when it pays', function () {
    Notification::fake();
    [$church, $owner] = feeChurch();
    $church->update(['trial_ends_at' => today()->addDays(6)]);
    $billing = app(BillingService::class);

    $billing->generateDueInvoices();
    $billing->enforce();
    Notification::assertSentTo($owner, InvoiceIssued::class, fn ($n) => $n->invoice->period_start->isSameDay(today()->addDays(6)));

    // Running it again the same day sends nothing twice.
    $billing->enforce();
    Notification::assertSentToTimes($owner, InvoiceIssued::class, 1);

    // Two days later, the day before the payment day: a reminder, once.
    $this->travel(5)->days();
    $billing->enforce();
    $billing->enforce();
    Notification::assertSentToTimes($owner, PaymentDueSoon::class, 1);

    $invoice = SubscriptionInvoice::firstOrFail();
    $billing->markInvoicePaid($invoice, 'MM-1', 'mtn_momo');
    Notification::assertSentTo($owner, InvoicePaid::class);
    expect($church->fresh()->billing_status)->toBe('active')->and($church->fresh()->paid_until)->not->toBeNull();
});

test('a church pays its fee with mobile money only', function () {
    [$church, $owner] = feeChurch();
    $church->update(['trial_ends_at' => today()->addDays(3)]);
    app(BillingService::class)->generateDueInvoices();
    $invoice = SubscriptionInvoice::firstOrFail();

    $this->actingAs($owner)->post(route('church-admin.billing.pay', [$church, $invoice]), ['method' => 'stripe', 'phone' => '0772123456'])->assertSessionHasErrors('method');
});

test('the owner and the admins are the people told about money and standing', function () {
    [$church, $owner] = feeChurch();
    $other = member();
    $church->staffMembers()->create(['user_id' => $other->id, 'is_admin' => false, 'status' => 'active']);
    $admin = member();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);

    expect($church->billingContacts()->pluck('id')->sort()->values()->all())->toBe(collect([$owner->id, $admin->id])->sort()->values()->all());
});

// ── Dormant churches ─────────────────────────────────────────────────────────────────────────────────────────────

function quietChurch(int $daysOld = 60): array
{
    $owner = member();
    $church = Church::factory()->create(['owner_id' => $owner->id, 'status' => 'active', 'created_at' => now()->subDays($daysOld), 'approved_at' => now()->subDays($daysOld)]);
    $church->staffMembers()->create(['user_id' => $owner->id, 'is_admin' => true, 'status' => 'active']);

    return [$church, $owner];
}

test('a church that stays quiet is warned, then blocked, and told each time', function () {
    Notification::fake();
    [$church, $owner] = quietChurch();
    $dormancy = app(DormancyService::class);

    expect($dormancy->run())->toBe(['warned' => 1, 'blocked' => 0, 'cleared' => 0]);
    Notification::assertSentTo($owner, ChurchQuiet::class, fn ($n) => $n->blockInDays === 14);
    expect($church->fresh()->dormant_warned_at)->not->toBeNull()->and($church->fresh()->status)->toBe('active');

    // The same day again, and a week later: nothing new.
    expect($dormancy->run())->toBe(['warned' => 0, 'blocked' => 0, 'cleared' => 0]);
    $this->travel(8)->days();
    expect($dormancy->run())->toBe(['warned' => 0, 'blocked' => 0, 'cleared' => 0]);

    // The warning period ends: blocked.
    $this->travel(7)->days();
    expect($dormancy->run())->toBe(['warned' => 0, 'blocked' => 1, 'cleared' => 0]);
    Notification::assertSentTo($owner, ChurchBlockedForInactivity::class);
    expect($church->fresh()->status)->toBe('suspended')->and($church->fresh()->dormant_blocked_at)->not->toBeNull();
});

test('a church that comes back to life after the warning is left open', function () {
    Notification::fake();
    [$church, $owner] = quietChurch();
    $dormancy = app(DormancyService::class);
    $dormancy->run();

    $this->travel(5)->days();
    $church->announcements()->create(['user_id' => $owner->id, 'title' => 'We are back', 'body' => 'Welcome home.', 'published_at' => now()]);

    $this->travel(10)->days();
    expect($dormancy->run())->toBe(['warned' => 0, 'blocked' => 0, 'cleared' => 1]);
    expect($church->fresh()->status)->toBe('active')->and($church->fresh()->dormant_warned_at)->toBeNull();
});

test('a church that is looked after, or brand new, is never warned', function () {
    Notification::fake();
    [$active, $owner] = quietChurch();
    $owner->forceFill(['last_login_at' => now()->subDays(2)])->save(); // the staff signed in this week
    [$busy] = quietChurch();
    $busy->subscriptions()->create(['user_id' => member()->id, 'status' => 'active', 'subscribed_at' => now()->subDays(3)]); // a new follower
    [$new] = quietChurch(5);

    expect(app(DormancyService::class)->run())->toBe(['warned' => 0, 'blocked' => 0, 'cleared' => 0]);
    Notification::assertNothingSent();
});

test('the super admin can change how long before a quiet church is warned and blocked', function () {
    $admin = superAdmin();
    $this->actingAs($admin)->put(route('super-admin.settings.update'), ['dormancy_warn_days' => 45, 'dormancy_block_days' => 10])->assertRedirect();

    expect(app(DormancyService::class)->warnAfterDays())->toBe(45)->and(app(DormancyService::class)->blockAfterDays())->toBe(10);
});

// ── The people who follow a church ──────────────────────────────────────────────────────────────────────────────

test('the church and the super admin see its followers in full', function () {
    [$church, $owner] = feeChurch();
    foreach ([['female', 1995, 'Lira'], ['male', 1980, 'Lira'], ['female', 2012, 'Gulu']] as $i => [$gender, $year, $city]) {
        $person = member(['gender' => $gender, 'date_of_birth' => "{$year}-06-01", 'city' => $city, 'last_login_at' => now()->subDays($i)]);
        $church->subscriptions()->create(['user_id' => $person->id, 'status' => 'active', 'stage' => 'member', 'source' => 'qr', 'subscribed_at' => now()->subDays($i + 1)]);
    }
    $left = member();
    $church->subscriptions()->create(['user_id' => $left->id, 'status' => 'left', 'subscribed_at' => now()->subDays(40)]);

    $stats = app(SubscriberStats::class)->forChurch($church);
    expect($stats['totals'])->toMatchArray(['followers' => 3, 'left' => 1, 'new_7' => 3])
        ->and(collect($stats['genders'])->pluck('count', 'label')->all())->toMatchArray(['Women' => 2, 'Men' => 1])
        ->and(collect($stats['ages'])->firstWhere('label', 'Under 18')['count'])->toBe(1)
        ->and(collect($stats['towns'])->first())->toBe(['label' => 'Lira', 'count' => 2])
        ->and($stats['activity']['opened_30'])->toBe(3)->and($stats['recent'])->toHaveCount(3);

    $this->actingAs($owner)->get(route('church-admin.subscribers', $church))->assertOk()->assertSee('Followers')->assertSee('Lira');
    $this->actingAs(superAdmin())->get(route('super-admin.subscribers.index'))->assertOk()->assertSee($church->name);
    $this->actingAs(superAdmin())->get(route('super-admin.subscribers.show', $church))->assertOk()->assertSee('Newest followers');

    $this->actingAs(member())->get(route('church-admin.subscribers', $church))->assertForbidden();
});

// ── A note to the owner for every new account ───────────────────────────────────────────────────────────────────

test('the owner is emailed about every new account', function () {
    Notification::fake();
    $admin = superAdmin();
    $person = member(['name' => 'Grace Akello']);

    expect(app(SignupAlerts::class)->notify($person))->toBe(1);
    Notification::assertSentOnDemand(NewSignup::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === mb_strtolower($admin->email));

    // A listed address replaces the super admins; the new person is never told about themselves; a super admin joining is not announced.
    app(SignupAlerts::class)->save(true, 'ot@example.com, partner@example.com');
    expect(app(SignupAlerts::class)->recipients())->toBe(['ot@example.com', 'partner@example.com'])->and(app(SignupAlerts::class)->notify($person))->toBe(2);
    expect(app(SignupAlerts::class)->notify($admin))->toBe(0);

    app(SignupAlerts::class)->save(false, '');
    expect(app(SignupAlerts::class)->notify($person))->toBe(0);
});

test('creating an account queues the alert, and the console stays quiet', function () {
    Bus::fake();
    member();
    Bus::assertNotDispatchedAfterResponse(SendSignupAlert::class);

    config(['faithfellows.signup_alerts_in_console' => true]);
    $person = member();
    Bus::assertDispatchedAfterResponse(SendSignupAlert::class, fn ($job) => $job->userId === $person->id);
});

test('the super admin chooses who is emailed about new accounts', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->put(route('super-admin.email.alerts'), ['enabled' => 1, 'emails' => 'one@example.com; two@example.com'])->assertRedirect();
    expect(app(SignupAlerts::class)->recipients())->toBe(['one@example.com', 'two@example.com']);

    $this->actingAs($admin)->put(route('super-admin.email.alerts'), ['enabled' => 1, 'emails' => 'not-an-email'])->assertSessionHasErrors('emails');
    $this->actingAs($admin)->get(route('super-admin.email.edit'))->assertOk()->assertSee('New sign-up emails');
});

// ── Inspiring messages on a schedule and right now ──────────────────────────────────────────────────────────────

function phoneFor(?User $user = null): User
{
    $user ??= member();
    DeviceToken::create(['user_id' => $user->id, 'token' => 'ExponentPushToken[abcdefghij'.$user->id.']', 'platform' => 'android', 'last_used_at' => now()]);

    return $user;
}

test('each part of the day is pushed once at its time, and only while it is still fresh', function () {
    Bus::fake();
    Inspiration::query()->delete(); // the starter messages the migration adds
    Inspiration::create(['slot' => 'morning', 'body' => 'Be strong and courageous.', 'is_active' => true]);
    Inspiration::create(['slot' => 'evening', 'body' => 'Peace I leave with you.', 'is_active' => true]);
    phoneFor();
    $broadcaster = app(InspirationBroadcaster::class);

    $this->travelTo(today()->setTime(6, 29));
    expect($broadcaster->sendDue())->toBe(0);

    $this->travelTo(today()->setTime(6, 31));
    expect($broadcaster->sendDue())->toBe(1);
    Bus::assertDispatchedAfterResponse(SendInspirationBatch::class, fn ($job) => str_contains($job->message['body'], 'Be strong') && $job->message['data'] === ['kind' => 'inspiration']);

    // Every minute the server checks, but the morning message does not go twice.
    $this->travelTo(today()->setTime(6, 40));
    expect($broadcaster->sendDue())->toBe(0);

    // The mid-morning part has no message on; the evening one went too late the day the server was down.
    $this->travelTo(today()->setTime(23, 0));
    expect($broadcaster->sendDue())->toBe(0);

    // Off means off.
    $broadcaster->save(false, $broadcaster->times());
    Cache::flush();
    $this->travelTo(today()->setTime(6, 31));
    expect($broadcaster->sendDue())->toBe(0);
});

test('the super admin sets the times and sends a message to everyone now', function () {
    Bus::fake();
    $admin = superAdmin();
    $one = phoneFor();
    phoneFor();
    $message = Inspiration::create(['slot' => 'morning', 'body' => 'The Lord is my light.', 'reference' => 'Psalm 27:1', 'is_active' => true]);

    $this->actingAs($admin)->put(route('super-admin.inspiration.schedule'), ['auto' => 1, 'times' => ['morning' => '05:45', 'midmorning' => '11:00', 'evening' => '19:15']])->assertRedirect();
    expect(app(InspirationBroadcaster::class)->times())->toBe(['morning' => '05:45', 'midmorning' => '11:00', 'evening' => '19:15']);
    $this->actingAs($admin)->put(route('super-admin.inspiration.schedule'), ['times' => ['morning' => '5:45']])->assertSessionHasErrors();

    $this->actingAs($admin)->post(route('super-admin.inspiration.send', $message))->assertRedirect()->assertSessionHas('status');
    Bus::assertDispatchedAfterResponse(SendInspirationBatch::class, fn ($job) => count($job->userIds) === 2 && str_contains($job->message['body'], 'Psalm 27:1'));

    // A double tap is not sent twice, and members cannot do this.
    Bus::fake();
    $this->actingAs($admin)->post(route('super-admin.inspiration.send', $message))->assertRedirect();
    Bus::assertNotDispatchedAfterResponse(SendInspirationBatch::class);
    $this->actingAs($one)->post(route('super-admin.inspiration.send', $message))->assertForbidden();
    $this->actingAs($admin)->get(route('super-admin.inspiration.index'))->assertOk()->assertSee('Send to phones');
});

test('only people who want daily inspiration get the push', function () {
    Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok', 'id' => 't1']]])]);
    $wants = phoneFor();
    $muted = phoneFor();
    NotificationPreference::create(['user_id' => $muted->id, 'church_id' => null, 'category' => 'inspiration', 'channels' => [], 'enabled' => false]);

    (new SendInspirationBatch([$wants->id, $muted->id], ['title' => 'Morning inspiration', 'body' => 'Be still.', 'data' => ['kind' => 'inspiration']]))->handle(app(DevicePushService::class), app(ExpoPushSender::class));

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => count($request->data()) === 1 && $request->data()[0]['to'] === 'ExponentPushToken[abcdefghij'.$wants->id.']');
});

// ── Why did the push not arrive? ───────────────────────────────────────────────────────────────────────────────

test('the push test shows what Expo said, and explains a missing Firebase key', function () {
    $admin = superAdmin();
    phoneFor($admin);

    Http::fake(['exp.host/--/api/v2/push/send' => Http::sequence()
        ->push(['data' => [['status' => 'ok', 'id' => 'ticket-1']]])
        ->push(['data' => [['status' => 'error', 'message' => 'bad key', 'details' => ['error' => 'InvalidCredentials']]]])]);
    $this->actingAs($admin)->post(route('super-admin.push.send'), ['email' => $admin->email])->assertRedirect();
    $this->actingAs($admin)->get(route('super-admin.push.index'))->assertOk()->assertSee('Accepted by Expo')->assertSee('Android');

    $this->actingAs($admin)->post(route('super-admin.push.send'), ['email' => $admin->email])->assertRedirect();
    $this->actingAs($admin)->get(route('super-admin.push.index'))->assertOk()->assertSee('Firebase (FCM V1) key');

    // A member who has not registered a phone gets a clear answer instead of nothing.
    $this->actingAs($admin)->post(route('super-admin.push.send'), ['email' => member(['email' => 'nophone@example.com'])->email])->assertSessionHasErrors('email');

    $this->actingAs(member())->get(route('super-admin.push.index'))->assertForbidden();
});

// ── Gmail: a wrong password is caught before it is saved, and a refusal says which account was tried ───────────

test('a password that is not a Gmail App Password is turned away before it is saved', function () {
    $admin = superAdmin();
    $form = ['enabled' => 1, 'host' => 'smtp.gmail.com', 'port' => 587, 'username' => 'faithfellowsofficial@gmail.com', 'from_address' => 'faithfellowsofficial@gmail.com', 'from_name' => 'FaithFellows'];

    // A normal account password (what a browser might fill in) is not 16 letters.
    $this->actingAs($admin)->put(route('super-admin.email.update'), $form + ['password' => 'MySuperAdminPass!2026'])->assertSessionHasErrors('password');
    expect(PlatformSetting::get('mail.password'))->toBeNull();

    // The App Password, with or without the spaces Google shows, is accepted and saved as 16 letters.
    $this->actingAs($admin)->put(route('super-admin.email.update'), $form + ['password' => 'abcd efgh ijkl mnop'])->assertSessionHasNoErrors();
    expect(PlatformSetting::get('mail.password'))->toBe('abcdefghijklmnop');
    $this->actingAs($admin)->get(route('super-admin.email.edit'))->assertOk()->assertSee('16 characters saved');
});

test('when Google refuses the sign-in the message names the account and says if the password is the wrong length', function () {
    $refusal = new RuntimeException('Expected response code "235" but got code "535", with message "535-5.7.8 Username and Password not accepted."');

    $wrongLength = MailSettings::explain($refusal, 'admin@faithfellows.com', 21);
    expect($wrongLength)->toContain('admin@faithfellows.com')->toContain('21 characters')->toContain('exactly 16 letters');

    $rightLength = MailSettings::explain($refusal, 'faithfellowsofficial@gmail.com', 16);
    expect($rightLength)->toContain('faithfellowsofficial@gmail.com')->not->toContain('characters')->toContain('Yes, it was me');
});
