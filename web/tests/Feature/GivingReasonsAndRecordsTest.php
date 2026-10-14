<?php

use App\Models\Church;
use App\Models\Donation;
use App\Models\GivingReason;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Notifications\Giving\DonationReceiptNotification;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Services\FinancialRecords;
use App\Services\GoogleSettings;
use App\Services\MailSettings;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/*
| Giving the church's way: the reasons a church lists, gifts clustered by reason in the financial records for accounting,
| the thank-you email every giver gets, and the Gmail account the super admin saves for sending it.
*/

beforeEach(function () {
    PaymentService::flush();
    config(['faithfellows.payments.sandbox' => true]);
});

/** @return array{0: Church, 1: User, 2: User} church, treasurer, giver */
function reasonChurch(): array
{
    $church = Church::factory()->create();
    $treasurer = member();
    $church->staffMembers()->create(['user_id' => $treasurer->id, 'is_admin' => true, 'status' => 'active']);
    $church->givingChannels()->create(['type' => 'mtn_momo', 'is_active' => true]);
    $giver = member(['phone' => '0772123456']);
    $church->subscriptions()->create(['user_id' => $giver->id, 'status' => 'active', 'subscribed_at' => now()]);

    return [$church, $treasurer, $giver];
}

function paidGift(Church $church, array $attributes = []): Donation
{
    return Donation::create(array_merge([
        'church_id' => $church->id, 'user_id' => null, 'giver_name' => 'A Giver', 'category' => 'offering', 'amount' => 10000, 'fee' => 200,
        'currency' => 'UGX', 'method' => 'mtn_momo', 'status' => 'succeeded', 'paid_at' => now(), 'receipt_number' => 'R'.random_int(100000, 999999),
    ], $attributes));
}

test('every church starts with a few giving reasons it can change', function () {
    $church = Church::factory()->create();

    expect($church->givingReasons()->ordered()->pluck('name')->all())->toBe(['Tithe', 'Offering', 'Thanksgiving', 'Building fund', 'Missions', 'Welfare']);
});

test('the give screen lists the church’s own reasons, and a gift keeps the reason it was given for', function () {
    [$church, , $giver] = reasonChurch();
    $church->givingReasons()->where('name', 'Welfare')->update(['is_active' => false]);
    $custom = $church->givingReasons()->create(['name' => 'Youth camp', 'category' => 'special', 'sort' => 9]);
    Sanctum::actingAs($giver, ['member']);

    $options = $this->getJson(route('api.v1.giving.options', $church))->assertOk()->json('data');
    expect(collect($options['reasons'])->pluck('name')->all())->toContain('Tithe', 'Youth camp')->not->toContain('Welfare')
        ->and($options['saved_phone'])->toBe('0772123456');

    $this->postJson(route('api.v1.giving.store', $church), ['amount' => 20000, 'method' => 'mtn_momo', 'phone' => '0772123456', 'reason_id' => $custom->id])->assertSuccessful();
    $gift = Donation::firstOrFail();
    expect($gift->reason)->toBe('Youth camp')->and($gift->giving_reason_id)->toBe($custom->id)->and($gift->category)->toBe('special')->and($gift->category_label)->toBe('Youth camp');

    // Removing the reason later does not change the record.
    $custom->delete();
    expect($gift->fresh()->category_label)->toBe('Youth camp');
});

test('a reason from another church, or a hidden one, cannot be used', function () {
    [$church, , $giver] = reasonChurch();
    $elsewhere = Church::factory()->create()->givingReasons()->first();
    $hidden = $church->givingReasons()->where('name', 'Welfare')->first();
    $hidden->update(['is_active' => false]);
    Sanctum::actingAs($giver, ['member']);

    foreach ([$elsewhere, $hidden] as $reason) {
        $this->postJson(route('api.v1.giving.store', $church), ['amount' => 5000, 'method' => 'mtn_momo', 'phone' => '0772123456', 'reason_id' => $reason->id])
            ->assertUnprocessable()->assertJsonValidationErrors('reason_id');
    }
    expect(Donation::count())->toBe(0);
});

test('only the church’s staff change its giving reasons', function () {
    [$church, $treasurer, $giver] = reasonChurch();

    $this->actingAs($giver)->get(route('church-admin.giving.reasons', $church))->assertForbidden();

    $this->actingAs($treasurer)->get(route('church-admin.giving.reasons', $church))->assertOk()->assertSee('Building fund');
    $this->actingAs($treasurer)->post(route('church-admin.giving.reasons.store', $church), ['name' => 'Harvest', 'category' => 'special', 'description' => 'Our annual harvest'])->assertRedirect();
    $harvest = $church->givingReasons()->where('name', 'Harvest')->firstOrFail();
    $this->actingAs($treasurer)->post(route('church-admin.giving.reasons.store', $church), ['name' => 'Harvest', 'category' => 'special'])->assertSessionHasErrors('name');

    $this->actingAs($treasurer)->put(route('church-admin.giving.reasons.update', [$church, $harvest]), ['name' => 'Harvest festival', 'category' => 'offering'])->assertRedirect();
    expect($harvest->fresh()->name)->toBe('Harvest festival')->and($harvest->fresh()->is_active)->toBeFalse(); // the switch was not sent

    $this->actingAs($treasurer)->delete(route('church-admin.giving.reasons.destroy', [$church, $harvest]))->assertRedirect();
    expect(GivingReason::find($harvest->id))->toBeNull();

    // Another church's reason is out of reach.
    $other = Church::factory()->create()->givingReasons()->first();
    $this->actingAs($treasurer)->delete(route('church-admin.giving.reasons.destroy', [$church, $other]))->assertNotFound();
});

test('the financial records cluster gifts by reason, campaign and method, and add up', function () {
    [$church, $treasurer] = reasonChurch();
    $tithe = $church->givingReasons()->where('name', 'Tithe')->first();
    $campaign = $church->campaigns()->create(['title' => 'Roof Fund', 'type' => 'building', 'goal_amount' => 100000, 'status' => 'active', 'visibility' => 'public']);

    paidGift($church, ['amount' => 50000, 'fee' => 1000, 'category' => 'tithe', 'reason' => 'Tithe', 'giving_reason_id' => $tithe->id]);
    paidGift($church, ['amount' => 30000, 'fee' => 600, 'category' => 'tithe', 'reason' => 'Tithe', 'giving_reason_id' => $tithe->id, 'method' => 'airtel_money']);
    paidGift($church, ['amount' => 20000, 'fee' => 400, 'category' => 'campaign', 'campaign_id' => $campaign->id]);
    paidGift($church, ['amount' => 5000, 'fee' => 0, 'category' => 'offering', 'method' => 'cash']);
    paidGift($church, ['amount' => 99999, 'status' => 'pending', 'paid_at' => null]);                       // not received yet
    paidGift($church, ['amount' => 70000, 'paid_at' => now()->subMonths(3)]);                                 // another period

    [$from, $to] = FinancialRecords::period('this_month');
    $report = app(FinancialRecords::class)->build($church, $from, $to);
    $byReason = $report['by_reason']->keyBy('label');

    expect($report['gross'])->toBe(105000.0)->and($report['fees'])->toBe(2000.0)->and($report['net'])->toBe(103000.0)->and($report['gifts'])->toBe(4)
        ->and($report['pending'])->toBe(['count' => 1, 'amount' => 99999.0])
        ->and($byReason['Tithe']['gross'])->toBe(80000.0)->and($byReason['Tithe']['gifts'])->toBe(2)->and($byReason['Tithe']['share'])->toBe(76.2)
        ->and($byReason['Fundraising: Roof Fund']['gross'])->toBe(20000.0)->and($byReason['Fundraising: Roof Fund']['kind'])->toBe('campaign')
        ->and($byReason['Offering']['gross'])->toBe(5000.0)
        ->and($report['by_reason']->first()['label'])->toBe('Tithe')
        ->and($report['by_method']->pluck('gross', 'label')->all())->toMatchArray(['MTN MoMo' => 70000.0, 'Airtel Money' => 30000.0, 'Cash' => 5000.0]);

    // Only online gifts that were not paid out yet count as waiting.
    expect($report['awaiting_payout'])->toBe(['count' => 4, 'amount' => 167800.0]);

    $this->actingAs($treasurer)->get(route('church-admin.giving.records', $church))->assertOk()->assertSee('Tithe')->assertSee('Fundraising: Roof Fund')->assertSee('105,000');
    $this->actingAs($treasurer)->get(route('church-admin.giving.records', [$church, 'period' => 'this_year', 'reason' => $tithe->id]))->assertOk();
});

test('the records export as a ledger, a summary and a PDF', function () {
    [$church, $treasurer] = reasonChurch();
    paidGift($church, ['amount' => 12345, 'reason' => 'Tithe', 'category' => 'tithe']);

    $ledger = $this->actingAs($treasurer)->get(route('church-admin.giving.records.export', $church))->assertOk();
    expect($ledger->headers->get('content-type'))->toContain('text/csv')->and($ledger->streamedContent())->toContain('Tithe')->toContain('12345');

    $summary = $this->actingAs($treasurer)->get(route('church-admin.giving.records.export', [$church, 'format' => 'summary']))->assertOk();
    expect($summary->streamedContent())->toContain('GIVEN FOR')->toContain('12345.00');

    $this->actingAs($treasurer)->get(route('church-admin.giving.records.export', [$church, 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('every giver gets a branded thank-you email with what they gave and what it was for', function () {
    [$church, , $giver] = reasonChurch();
    $gift = paidGift($church, ['user_id' => $giver->id, 'amount' => 25000, 'reason' => 'Building fund', 'category' => 'building_fund', 'receipt_number' => 'FF-000042']);
    $giver->forceFill(['name' => 'Grace Akello'])->save();

    $mail = (new DonationReceiptNotification($gift->fresh(['church', 'campaign'])))->toMail($giver);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Thank you for your gift to '.$church->name)
        ->and($html)->toContain('Thank you, Grace!')->toContain('UGX 25,000')->toContain($church->name)->toContain('Building fund')->toContain('FF-000042')->toContain('2 Corinthians 9:7');
});

test('the thank-you email goes out when a gift succeeds', function () {
    Notification::fake();
    [$church, , $giver] = reasonChurch();
    $reason = $church->givingReasons()->first();
    Sanctum::actingAs($giver, ['member']);

    $this->postJson(route('api.v1.giving.store', $church), ['amount' => 10000, 'method' => 'mtn_momo', 'phone' => '0772123456', 'reason_id' => $reason->id])->assertSuccessful();
    $gift = Donation::firstOrFail();
    $this->travel(5)->seconds();
    $this->getJson(route('api.v1.giving.show', $gift->reference))->assertOk()->assertJsonPath('data.status', 'succeeded');

    Notification::assertSentTo($giver, DonationReceiptNotification::class, fn ($n, $channels) => in_array('mail', $channels, true));
});

test('the super admin saves the Gmail account and the password stays secret', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->get(route('super-admin.email.edit'))->assertOk()->assertSee('faithfellowsofficial@gmail.com');

    $this->actingAs($admin)->put(route('super-admin.email.update'), [
        'enabled' => 1, 'host' => 'smtp.gmail.com', 'port' => 587, 'username' => 'faithfellowsofficial@gmail.com', 'password' => 'abcd efgh ijkl mnop',
        'from_address' => 'faithfellowsofficial@gmail.com', 'from_name' => 'FaithFellows',
    ])->assertRedirect();

    expect(MailSettings::active())->toBeTrue()
        ->and(PlatformSetting::where('key', 'mail.password')->first()->value)->not->toContain('abcd')
        ->and(PlatformSetting::get('mail.password'))->toBe('abcdefghijklmnop');

    MailSettings::apply();
    expect(config('mail.default'))->toBe('smtp')->and(config('mail.mailers.smtp.host'))->toBe('smtp.gmail.com')->and(config('mail.mailers.smtp.port'))->toBe(587)
        ->and(config('mail.mailers.smtp.username'))->toBe('faithfellowsofficial@gmail.com')->and(config('mail.mailers.smtp.password'))->toBe('abcdefghijklmnop')
        ->and(config('mail.from.address'))->toBe('faithfellowsofficial@gmail.com');

    // The password is never put back into the page; leaving the box empty keeps it.
    $this->actingAs($admin)->get(route('super-admin.email.edit'))->assertOk()->assertDontSee('abcdefghijklmnop')->assertDontSee('abcd efgh');
    $this->actingAs($admin)->put(route('super-admin.email.update'), [
        'enabled' => 1, 'host' => 'smtp.gmail.com', 'port' => 587, 'username' => 'faithfellowsofficial@gmail.com', 'password' => '',
        'from_address' => 'faithfellowsofficial@gmail.com', 'from_name' => 'FaithFellows',
    ])->assertRedirect();
    expect(PlatformSetting::get('mail.password'))->toBe('abcdefghijklmnop');

    // Church staff and members cannot open it.
    $this->actingAs(member())->get(route('super-admin.email.edit'))->assertForbidden();
});

test('a test email is sent through the saved account, and a failure is explained in plain words', function () {
    $admin = superAdmin();
    $this->actingAs($admin)->post(route('super-admin.email.test'), ['to' => 'me@example.com'])->assertSessionHasErrors('to'); // nothing saved yet: only a log

    MailSettings::save(['enabled' => true, 'host' => '127.0.0.1', 'port' => '2525', 'username' => 'faithfellowsofficial@gmail.com', 'password' => 'abcdefghijklmnop', 'from_address' => 'faithfellowsofficial@gmail.com', 'from_name' => 'FaithFellows']);
    PlatformSetting::put('mail.port', '587');

    // Nothing is listening on this host, so the send fails, and the page says so without leaking the password.
    PlatformSetting::put('mail.host', '127.0.0.1');
    $response = $this->actingAs($admin)->post(route('super-admin.email.test'), ['to' => 'me@example.com']);
    $response->assertSessionHasErrors('to');
    expect(collect(session('errors')->get('to'))->implode(' '))->not->toContain('abcdefghijklmnop');
});

test('finance staff manage the reasons from the phone app, and the summary clusters this month by reason', function () {
    [$church, $treasurer, $giver] = reasonChurch();
    paidGift($church, ['amount' => 40000, 'fee' => 800, 'category' => 'tithe', 'reason' => 'Tithe']);
    paidGift($church, ['amount' => 10000, 'fee' => 200, 'category' => 'offering', 'reason' => 'Offering']);

    Sanctum::actingAs($giver, ['member']);
    $this->getJson(route('api.v1.admin.giving.reasons.index', $church))->assertForbidden();

    Sanctum::actingAs($treasurer, ['member']);
    $added = $this->postJson(route('api.v1.admin.giving.reasons.store', $church), ['name' => 'Youth camp', 'description' => 'Camp fees'])->assertCreated()->json('data');
    $this->postJson(route('api.v1.admin.giving.reasons.store', $church), ['name' => 'Youth camp'])->assertUnprocessable();
    $this->putJson(route('api.v1.admin.giving.reasons.update', [$church, $added['id']]), ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);

    $list = collect($this->getJson(route('api.v1.admin.giving.reasons.index', $church))->assertOk()->json('data'));
    expect($list->pluck('name')->all())->toContain('Tithe', 'Youth camp')->and($list->firstWhere('name', 'Tithe')['total'])->toEqual(0);

    $summary = $this->getJson(route('api.v1.admin.giving.summary', $church))->assertOk()->json('data');
    expect(collect($summary['by_reason'])->pluck('label')->all())->toBe(['Tithe', 'Offering'])->and($summary['fees_month'])->toEqual(1000)->and($summary['net_month'])->toEqual(49000);

    $this->deleteJson(route('api.v1.admin.giving.reasons.destroy', [$church, $added['id']]))->assertOk();
    expect(GivingReason::find($added['id']))->toBeNull();
});

test('the super admin saves the Google client and sign-in uses it', function () {
    config(['services.google.client_ids' => [], 'services.google.client_secret' => null]);
    $admin = superAdmin();

    $this->actingAs($admin)->get(route('super-admin.google.edit'))->assertOk()->assertSee('/auth/google/app/callback');
    $this->actingAs($admin)->put(route('super-admin.google.update'), ['client_id' => 'not-a-client'])->assertSessionHasErrors('client_id');

    $this->actingAs($admin)->put(route('super-admin.google.update'), ['client_id' => '1096890265639-abc123def456.apps.googleusercontent.com', 'client_secret' => 'GOCSPX-test-secret'])->assertRedirect();
    expect(PlatformSetting::where('key', 'google.client_secret')->first()->value)->not->toContain('GOCSPX');

    GoogleSettings::apply();
    expect(config('services.google.client_ids'))->toBe(['1096890265639-abc123def456.apps.googleusercontent.com'])->and(config('services.google.client_secret'))->toBe('GOCSPX-test-secret')
        ->and(app(GoogleIdTokenVerifier::class)->enabled())->toBeTrue();

    $this->actingAs($admin)->get(route('super-admin.google.edit'))->assertOk()->assertDontSee('GOCSPX-test-secret');
    $this->actingAs(member())->get(route('super-admin.google.edit'))->assertForbidden();
});
