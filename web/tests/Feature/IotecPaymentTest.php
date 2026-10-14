<?php

use App\Models\Church;
use App\Models\Donation;
use App\Services\Payments\Drivers\IotecDriver;
use App\Services\Payments\Drivers\SandboxDriver;
use App\Services\PaymentService;
use App\Support\MobileMoneyPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

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
