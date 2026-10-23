<?php

use App\Models\Church;
use App\Models\Donation;
use App\Services\DonationService;
use App\Services\Payments\Drivers\IotecDriver;
use App\Services\Payments\Drivers\SandboxDriver;
use App\Services\PaymentService;
use App\Support\MobileMoneyPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;

/*
| ioTec Pay: mobile money collected inside the app. The giver enters a number and a PIN prompt appears on their phone;
| the app then asks the server for the result. The sandbox (local machines only) uses the test wallet and ioTec's test
| currency, so no real money moves.
*/

beforeEach(function () {
    Cache::flush();
    PaymentService::flush();
    config([
        'faithfellows.payments.sandbox' => false,
        'services.iotec.client_id' => 'pay-test', 'services.iotec.client_secret' => 'secret', 'services.iotec.wallet_id' => 'live-wallet',
        'services.iotec.test_wallet_id' => 'test-wallet', 'services.iotec.sandbox' => false, 'services.iotec.ipn_secret' => '', 'services.iotec.webhook_secret' => '',
    ]);
    // One set of fake replies for the whole test; each test says what ioTec answers.
    $GLOBALS['iotec'] = ['collect' => [], 'collect_code' => 200, 'status' => []];
    Http::fake([
        'id.iotec.io/*' => Http::response(['access_token' => 'tok', 'expires_in' => 300]),
        'pay.iotec.io/api/collections/collect' => fn () => Http::response($GLOBALS['iotec']['collect'], $GLOBALS['iotec']['collect_code']),
        'pay.iotec.io/api/collections/status/*' => fn () => Http::response($GLOBALS['iotec']['status']),
    ]);
});

function ioGift(string $phone = '0772123456', int $amount = 25000): Donation
{
    $church = Church::factory()->create();
    $giver = member(['phone' => $phone]);

    return Donation::create(['church_id' => $church->id, 'user_id' => $giver->id, 'category' => 'tithe', 'amount' => $amount, 'currency' => 'UGX', 'method' => 'mtn_momo', 'status' => 'pending', 'phone' => $phone, 'reference' => 'FF-TEST-'.uniqid()]);
}

function fakeIotec(array $collect, array $status = [], int $code = 200): void
{
    $GLOBALS['iotec'] = ['collect' => $collect, 'collect_code' => $code, 'status' => $status];
}

test('mobile money goes through ioTec when it is the only gateway set up, and other methods stay on the sandbox', function () {
    expect(PaymentService::driver('mtn_momo'))->toBeInstanceOf(IotecDriver::class)
        ->and(PaymentService::driver('airtel_money'))->toBeInstanceOf(IotecDriver::class)
        ->and(PaymentService::driver('iotec'))->toBeInstanceOf(IotecDriver::class)
        ->and(PaymentService::driver('stripe'))->toBeInstanceOf(SandboxDriver::class);

    PaymentService::flush();
    config(['services.iotec.client_secret' => '']);
    expect(PaymentService::driver('mtn_momo'))->toBeInstanceOf(SandboxDriver::class);
});

test('a gift sends a collection to the giver\'s phone with the live wallet and the church\'s currency', function () {
    fakeIotec(['id' => 'tx-1', 'status' => 'Pending']);
    $gift = ioGift('0772 123 456');

    $result = PaymentService::driver('mtn_momo')->charge($gift);

    expect($result->status)->toBe('pending')->and($result->providerRef)->toBe('tx-1');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/collections/collect')
        && $r['category'] === 'MobileMoney' && $r['currency'] === 'UGX' && $r['walletId'] === 'live-wallet'
        && $r['payer'] === '256772123456' && $r['amount'] === 25000 && str_starts_with($r['externalId'], 'FF-TEST-') && $r['transactionChargesCategory'] === 'ChargeWallet');
});

test('in the sandbox the test wallet and the test currency are used', function () {
    config(['services.iotec.sandbox' => true]);
    fakeIotec(['id' => 'tx-2', 'status' => 'Pending']);

    PaymentService::driver('mtn_momo')->charge(ioGift('0111777771', 1000));

    Http::assertSent(fn ($r) => str_contains($r->url(), '/collections/collect') && $r['walletId'] === 'test-wallet' && $r['currency'] === 'ITX' && $r['payer'] === '256111777771');
});

test('ioTec can settle at once, or refuse, and the reason is passed on', function () {
    fakeIotec(['id' => 'tx-3', 'status' => 'Success']);
    expect(PaymentService::driver('mtn_momo')->charge(ioGift())->status)->toBe('succeeded');

    fakeIotec(['id' => 'tx-4', 'status' => 'Failed', 'statusMessage' => 'Insufficient funds']);
    $failed = PaymentService::driver('mtn_momo')->charge(ioGift());
    expect($failed->status)->toBe('failed')->and($failed->message)->toBe('Insufficient funds');

    fakeIotec(['message' => 'Invalid payer'], [], 400);
    expect(PaymentService::driver('mtn_momo')->charge(ioGift())->message)->toBe('Invalid payer');
});

test('the app\'s question "has it been paid?" maps ioTec\'s statuses', function () {
    $driver = PaymentService::driver('mtn_momo');

    fakeIotec([], ['id' => 'tx-1', 'status' => 'Success', 'externalId' => 'FF-TEST-001']);
    expect($driver->verify('tx-1')->status)->toBe('succeeded');

    foreach (['Failed', 'Cancelled', 'Rejected'] as $status) {
        fakeIotec([], ['id' => 'tx-1', 'status' => $status, 'statusMessage' => 'Declined']);
        expect($driver->verify('tx-1')->status)->toBe('failed');
    }

    fakeIotec([], ['id' => 'tx-1', 'status' => 'Pending', 'externalId' => 'FF-TEST-001']);
    $pending = $driver->verify('tx-1');
    expect($pending->status)->toBe('pending')->and($pending->reference)->toBe('FF-TEST-001');
});

test('ioTec\'s notice must carry the secret when one is set, and only identifies the gift', function () {
    config(['services.iotec.ipn_secret' => 'shh']);
    PaymentService::flush();
    $driver = PaymentService::driver('iotec');

    $notice = fn (array $headers = [], string $query = '') => Request::create('/webhooks/iotec'.$query, 'POST', ['externalId' => 'FF-TEST-001', 'id' => 'tx-1'], [], [], collect($headers)->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all());

    expect($driver->webhook($notice()))->toBeNull()
        ->and($driver->webhook($notice(['X-IPN-Secret' => 'wrong'])))->toBeNull()
        ->and($driver->webhook($notice(['X-IPN-Secret' => 'shh']))?->reference)->toBe('FF-TEST-001')
        ->and($driver->webhook($notice([], '?secret=shh'))?->reference)->toBe('FF-TEST-001');
});

test('ioTec test numbers are accepted in the give form only while the sandbox is on', function () {
    $rule = fn () => Validator::make(['phone' => '0111777771'], ['phone' => [MobileMoneyPhone::rule()]])->passes();

    expect($rule())->toBeFalse()->and(MobileMoneyPhone::pattern())->not->toContain('0111');
    config(['services.iotec.sandbox' => true]);
    expect($rule())->toBeTrue()->and(MobileMoneyPhone::pattern())->toContain('0111')
        ->and(Validator::make(['phone' => '0772123456'], ['phone' => [MobileMoneyPhone::rule()]])->passes())->toBeTrue()
        ->and(Validator::make(['phone' => '0555123456'], ['phone' => [MobileMoneyPhone::rule()]])->passes())->toBeFalse();
});

test('the church is named on the prompt the giver sees and beside the payment in ioTec', function () {
    fakeIotec(['id' => 'tx-9', 'status' => 'Pending']);
    $gift = ioGift('0772123456', 20000);
    $gift->church->update(['name' => 'Grace Community Church Lira']);

    $result = PaymentService::driver('mtn_momo')->charge($gift->fresh());

    Http::assertSent(fn ($r) => str_contains($r->url(), '/collections/collect')
        && str_starts_with($r['payerNote'], 'Gift to Grace Community Church Lira') && strlen($r['payerNote']) <= 60
        && str_contains($r['payeeNote'], $gift->reference) && str_contains($r['payeeNote'], 'Grace Community Church Lira'));
    expect($result->message)->toContain('ioTec Pay')->toContain('Grace Community Church Lira');
});

// ── A gift that was paid is found, whether or not the callback, the phone, the queue or the cron job told us ─────

/** A church with a finance admin, a campaign, and a mobile money gift to the campaign that is still waiting, made a few minutes ago. */
function waitingCampaignGift(int $amount = 1000, string $status = 'processing'): array
{
    $church = Church::factory()->create();
    $admin = member();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);
    $giver = member(['phone' => '0772123456']);
    $campaign = $church->campaigns()->create(['title' => 'Roof Fund', 'type' => 'building', 'goal_amount' => 100000, 'status' => 'active', 'visibility' => 'public']);
    $gift = Donation::create(['church_id' => $church->id, 'user_id' => $giver->id, 'campaign_id' => $campaign->id, 'category' => 'campaign', 'amount' => $amount, 'currency' => 'UGX',
        'method' => 'mtn_momo', 'status' => $status, 'phone' => '0772123456', 'provider_ref' => 'tx-'.uniqid(), 'reference' => 'FF-TEST-'.uniqid()]);
    Donation::whereKey($gift->id)->update(['created_at' => now()->subMinutes(5)]);

    return [$church, $admin, $giver, $campaign, $gift->fresh()];
}

test('opening the finance page asks ioTec about gifts still waiting, and a paid gift is counted at once', function () {
    [$church, $admin, , $campaign, $gift] = waitingCampaignGift(1000);
    fakeIotec([], ['id' => 'tx-77', 'status' => 'Success', 'externalId' => $gift->reference]);

    $page = $this->actingAs($admin)->get(route('church-admin.giving.index', $church))->assertOk();

    expect($gift->fresh()->status)->toBe('succeeded')->and($gift->fresh()->receipt_number)->not->toBeNull()
        ->and((float) $campaign->fresh()->raised_amount)->toBe(1000.0)->and($campaign->fresh()->givers_count)->toBe(1);
    // The page was built after the gift was confirmed: the figures include it.
    $page->assertSee('UGX 1,000')->assertDontSee('waiting to be confirmed');
});

test('Check payments says what happened, and a gift the giver has not approved yet stays waiting', function () {
    [$church, $admin, , $campaign, $gift] = waitingCampaignGift(1000, 'pending');
    fakeIotec([], ['id' => 'tx-77', 'status' => 'Pending', 'externalId' => $gift->reference]);

    $this->actingAs($admin)->post(route('church-admin.giving.refresh', $church))->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, '1 payment is still waiting'));
    expect($gift->fresh()->status)->toBe('pending')->and((float) $campaign->fresh()->raised_amount)->toBe(0.0);

    // The giver approves; the button brings it in and says so.
    fakeIotec([], ['id' => 'tx-77', 'status' => 'Success', 'externalId' => $gift->reference]);
    $this->actingAs($admin)->post(route('church-admin.giving.refresh', $church))->assertSessionHas('success', fn ($m) => str_contains($m, '1 payment was confirmed'));
    expect($gift->fresh()->status)->toBe('succeeded')->and((float) $campaign->fresh()->raised_amount)->toBe(1000.0);

    // A gift ioTec says failed is settled too, and is not counted.
    [$church2, $admin2, , $campaign2, $failed] = waitingCampaignGift(2000);
    fakeIotec([], ['id' => 'tx-77', 'status' => 'Failed', 'statusMessage' => 'Declined', 'externalId' => $failed->reference]);
    $this->actingAs($admin2)->post(route('church-admin.giving.refresh', $church2))->assertRedirect();
    expect($failed->fresh()->status)->toBe('failed')->and((float) $campaign2->fresh()->raised_amount)->toBe(0.0);

    // Only people who may see the finances can ask.
    $this->actingAs(member())->post(route('church-admin.giving.refresh', $church))->assertForbidden();
});

test('a gift is not asked about again and again: very new gifts and old ones are left to the other checks', function () {
    [$church, $admin, , , $gift] = waitingCampaignGift(1000);
    Donation::whereKey($gift->id)->update(['created_at' => now()->subSeconds(10)]);
    fakeIotec([], ['id' => 'tx-77', 'status' => 'Success', 'externalId' => $gift->reference]);

    $this->actingAs($admin)->get(route('church-admin.giving.index', $church))->assertOk();
    expect($gift->fresh()->status)->toBe('processing'); // 10 seconds old: the giver is probably still looking at the prompt

    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/collections/status/'));
});

test('a giver who closed the app before approving finds the gift paid when they open their history', function () {
    [, , $giver, $campaign, $gift] = waitingCampaignGift(1000);
    fakeIotec([], ['id' => 'tx-77', 'status' => 'Success', 'externalId' => $gift->reference]);

    Sanctum::actingAs($giver, ['member']);
    $rows = $this->getJson(route('api.v1.giving.index'))->assertOk()->json('data');

    expect($rows[0]['status'])->toBe('succeeded')->and($gift->fresh()->status)->toBe('succeeded')->and((float) $campaign->fresh()->raised_amount)->toBe(1000.0);
});

test('a campaign counts a paid gift at once, even with no queue worker, and corrects itself when it is opened', function () {
    Queue::fake();
    [$church, $admin, $giver, $campaign, $gift] = waitingCampaignGift(1000);

    app(DonationService::class)->markSucceeded($gift, 'tx-77');
    expect((float) $campaign->fresh()->raised_amount)->toBe(1000.0); // not waiting for a queued job

    // A total that fell behind (a job that never ran) is put right the next time the campaign is looked at, by the church and by members.
    $campaign->forceFill(['raised_amount' => 0, 'givers_count' => 0])->save();
    $this->actingAs($admin)->get(route('church-admin.campaigns.index', $church))->assertOk();
    expect((float) $campaign->fresh()->raised_amount)->toBe(1000.0);

    $campaign->forceFill(['raised_amount' => 0])->save();
    Sanctum::actingAs($giver, ['member']);
    $this->getJson(route('api.v1.campaigns.show', $campaign->id))->assertOk()->assertJsonPath('data.raised', 1000);
    expect((float) $campaign->fresh()->raised_amount)->toBe(1000.0);
});

test('ioTec\'s notice keeps ioTec\'s id for a gift that had none, so the payment can be looked up', function () {
    [, , , , $gift] = waitingCampaignGift(1000, 'pending');
    Donation::whereKey($gift->id)->update(['provider_ref' => null]);
    fakeIotec([], ['id' => 'tx-notice', 'status' => 'Success', 'externalId' => $gift->reference]);

    $this->postJson('/webhooks/iotec', ['externalId' => $gift->reference, 'id' => 'tx-notice', 'status' => 'Success'])->assertOk()->assertJson(['status' => 'succeeded']);

    expect($gift->fresh()->provider_ref)->toBe('tx-notice')->and($gift->fresh()->status)->toBe('succeeded');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/collections/status/tx-notice'));
});
