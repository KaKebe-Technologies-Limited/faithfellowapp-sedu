<?php

use App\Events\DonationSucceeded;
use App\Models\BankStatementLine;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\Donation;
use App\Models\EventRsvp;
use App\Models\Pledge;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Notifications\Giving\CampaignMilestoneReached;
use App\Notifications\Giving\DonationReceiptNotification;
use App\Notifications\Giving\DonationReceived;
use App\Services\BillingService;
use App\Services\CampaignService;
use App\Services\DonationService;
use App\Services\Payments\Drivers\StripeDriver;
use App\Services\PaymentService;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    PaymentService::flush();
    config(['faithfellows.payments.sandbox' => true]);
});

/** @return array{0: Church, 1: User, 2: User, 3: User} church, admin, finance, giver */
function givingChurch(): array
{
    $admin = member();
    $church = Church::factory()->create();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);
    $role = $church->roles()->create(['name' => 'Finance', 'slug' => 'finance', 'permissions' => ['giving.view', 'giving.manage', 'campaigns.manage']]);
    $finance = member();
    $church->staffMembers()->create(['user_id' => $finance->id, 'church_role_id' => $role->id, 'status' => 'active']);
    foreach (['mtn_momo', 'airtel_money', 'stripe', 'bank'] as $type) {
        $church->givingChannels()->create(['type' => $type, 'is_active' => true]);
    }
    $giver = member(['phone' => '0772123456']);
    $church->subscriptions()->create(['user_id' => $giver->id, 'status' => 'active', 'subscribed_at' => now()]);

    return [$church, $admin, $finance, $giver];
}

function makeCampaign(Church $church, float $goal = 100000): Campaign
{
    $campaign = $church->campaigns()->create(['title' => 'Roof Fund', 'type' => 'building', 'goal_amount' => $goal, 'status' => 'active', 'visibility' => 'public']);
    app(CampaignService::class)->createDefaultMilestones($campaign);

    return $campaign;
}

test('mobile money gift goes pending then succeeds with receipt, finance alert and campaign progress', function () {
    Notification::fake();
    [$church, , $finance, $giver] = givingChurch();
    $campaign = makeCampaign($church, 100000);

    $this->actingAs($giver)->post(route('give.store', $church), [
        'amount' => 60000, 'method' => 'mtn_momo', 'phone' => '0772123456', 'campaign_id' => $campaign->id, 'show_on_leaderboard' => 1,
    ])->assertRedirect();

    $donation = Donation::firstOrFail();
    expect($donation->status)->toBe('processing')->and($donation->category)->toBe('campaign');

    $this->travel(5)->seconds();
    $this->actingAs($giver)->getJson(route('give.status.check', $donation->reference))->assertJson(['status' => 'succeeded']);

    $donation->refresh();
    expect($donation->receipt_number)->not->toBeNull()->and($donation->paid_at)->not->toBeNull();
    Notification::assertSentTo($giver, DonationReceiptNotification::class);
    Notification::assertSentTo($finance, DonationReceived::class);

    $campaign->refresh();
    expect((float) $campaign->raised_amount)->toBe(60000.0)
        ->and($campaign->givers_count)->toBe(1)
        ->and($campaign->milestones()->whereNotNull('reached_at')->pluck('percent')->all())->toBe([25, 50]);
    Notification::assertSentTo($giver, CampaignMilestoneReached::class, fn ($n) => $n->percent === 50);
});

test('declined mobile money payments are marked failed', function () {
    [$church, , , $giver] = givingChurch();

    $this->actingAs($giver)->post(route('give.store', $church), ['amount' => 5000, 'category' => 'tithe', 'method' => 'airtel_money', 'phone' => '0701230000']);
    $this->travel(5)->seconds();
    $this->actingAs($giver)->getJson(route('give.status.check', Donation::first()->reference))->assertJson(['status' => 'failed']);
});

test('completing a donation is idempotent', function () {
    Event::fake([DonationSucceeded::class]);
    [$church, , , $giver] = givingChurch();
    $donation = $church->donations()->create(['user_id' => $giver->id, 'category' => 'tithe', 'amount' => 1000, 'method' => 'mtn_momo', 'status' => 'pending']);
    $service = app(DonationService::class);

    $service->markSucceeded($donation, 'MTN-123');
    $service->markSucceeded($donation->fresh(), 'MTN-123');
    $service->markSucceeded($donation->fresh(), 'MTN-123');

    Event::assertDispatchedTimes(DonationSucceeded::class, 1);
});

test('a provider reference can only complete one donation', function () {
    Event::fake([DonationSucceeded::class]);
    [$church, , , $giver] = givingChurch();
    $a = $church->donations()->create(['user_id' => $giver->id, 'category' => 'tithe', 'amount' => 1000, 'method' => 'mtn_momo', 'status' => 'pending']);
    $b = $church->donations()->create(['user_id' => $giver->id, 'category' => 'tithe', 'amount' => 1000, 'method' => 'mtn_momo', 'status' => 'pending']);

    app(DonationService::class)->markSucceeded($a, 'SAME-REF');
    app(DonationService::class)->markSucceeded($b, 'SAME-REF');

    expect($b->fresh()->status)->toBe('pending');
    Event::assertDispatchedTimes(DonationSucceeded::class, 1);
});

test('card gifts redirect to checkout and complete when approved', function () {
    [$church, , , $giver] = givingChurch();

    $response = $this->actingAs($giver)->post(route('give.store', $church), ['amount' => 25000, 'category' => 'missions', 'method' => 'stripe']);
    $donation = Donation::firstOrFail();
    $response->assertRedirect(route('give.sandbox', $donation->reference));

    $this->actingAs($giver)->post(route('give.sandbox', $donation->reference), ['decision' => 'approve'])->assertRedirect(route('give.status', $donation->reference));
    expect($donation->fresh()->status)->toBe('succeeded');
});

test('methods the church has not enabled are rejected', function () {
    [$church, , , $giver] = givingChurch();
    $church->givingChannels()->where('type', 'airtel_money')->update(['is_active' => false]);

    $this->actingAs($giver)->post(route('give.store', $church), ['amount' => 5000, 'category' => 'tithe', 'method' => 'airtel_money', 'phone' => '0701234567'])
        ->assertSessionHasErrors('method');
});

test('payment webhooks re-verify with the gateway and are idempotent', function () {
    Notification::fake();
    [$church, , , $giver] = givingChurch();
    $this->actingAs($giver)->post(route('give.store', $church), ['amount' => 5000, 'category' => 'tithe', 'method' => 'mtn_momo', 'phone' => '0772123456']);
    $donation = Donation::firstOrFail();
    auth()->logout();

    // A forged "SUCCESSFUL" callback before the gateway confirms changes nothing.
    $this->postJson('/webhooks/mtn-momo', ['externalId' => $donation->reference, 'status' => 'SUCCESSFUL'])->assertOk();
    expect($donation->fresh()->status)->not->toBe('succeeded');

    $this->travel(5)->seconds();
    $this->postJson('/webhooks/mtn-momo', ['externalId' => $donation->reference])->assertJson(['status' => 'succeeded']);
    $this->postJson('/webhooks/mtn-momo', ['externalId' => $donation->reference])->assertJson(['status' => 'succeeded']);
    Notification::assertSentToTimes($giver, DonationReceiptNotification::class, 1);
});

test('stripe webhook signatures are verified', function () {
    $payload = json_encode(['type' => 'checkout.session.completed']);
    $secret = 'whsec_test';
    $t = time();
    $valid = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, $secret);

    expect(StripeDriver::validSignature($payload, $valid, $secret))->toBeTrue()
        ->and(StripeDriver::validSignature($payload, 't='.$t.',v1=forged', $secret))->toBeFalse()
        ->and(StripeDriver::validSignature($payload, 't='.($t - 3600).',v1='.hash_hmac('sha256', ($t - 3600).'.'.$payload, $secret), $secret))->toBeFalse();
});

test('reaching the goal posts a celebration update', function () {
    Notification::fake();
    [$church, , , $giver] = givingChurch();
    $campaign = makeCampaign($church, 10000);
    $donation = $church->donations()->create(['user_id' => $giver->id, 'campaign_id' => $campaign->id, 'category' => 'campaign', 'amount' => 10000, 'method' => 'cash', 'status' => 'pending']);

    app(DonationService::class)->markSucceeded($donation);

    $campaign->refresh();
    expect($campaign->goal_reached_at)->not->toBeNull()->and($campaign->updates()->count())->toBe(1);
    Notification::assertSentTo($giver, CampaignMilestoneReached::class, fn ($n) => $n->percent === 100);
});

test('pledge payments update the pledge balance', function () {
    [$church, , , $giver] = givingChurch();
    $pledge = Pledge::create(['church_id' => $church->id, 'user_id' => $giver->id, 'amount' => 20000, 'frequency' => 'monthly', 'installments' => 2, 'start_date' => today()]);
    $donation = $church->donations()->create(['user_id' => $giver->id, 'pledge_id' => $pledge->id, 'category' => 'project', 'amount' => 20000, 'method' => 'cash', 'status' => 'pending']);

    app(DonationService::class)->markSucceeded($donation);

    expect((float) $pledge->fresh()->paid_amount)->toBe(20000.0)->and($pledge->fresh()->status)->toBe('fulfilled');
});

test('finance can record cash and refund; other staff cannot refund', function () {
    Notification::fake();
    [$church, , $finance, $giver] = givingChurch();

    $this->actingAs($finance)->post(route('church-admin.giving.record', $church), ['amount' => 15000, 'category' => 'offering', 'method' => 'cash', 'giver_name' => 'Loose offering'])
        ->assertRedirect();
    $cash = Donation::firstOrFail();
    expect($cash->status)->toBe('succeeded')->and($cash->receipt_number)->not->toBeNull()->and($cash->recorded_by)->toBe($finance->id);

    $editor = member();
    $role = $church->roles()->create(['name' => 'Editor', 'slug' => 'editor', 'permissions' => ['content.manage']]);
    $church->staffMembers()->create(['user_id' => $editor->id, 'church_role_id' => $role->id, 'status' => 'active']);
    $this->actingAs($editor)->post(route('church-admin.giving.refund', [$church, $cash]), ['reason' => 'x'])->assertForbidden();

    $this->actingAs($finance)->post(route('church-admin.giving.refund', [$church, $cash]), ['reason' => 'Double entry'])->assertRedirect();
    expect($cash->fresh()->status)->toBe('refunded');
});

test('finance staff cannot see another church’s transactions', function () {
    [$churchA, , $financeA] = givingChurch();
    [$churchB] = givingChurch();
    $donationB = $churchB->donations()->create(['category' => 'tithe', 'amount' => 1000, 'method' => 'cash', 'status' => 'succeeded']);

    $this->actingAs($financeA)->get(route('church-admin.giving.index', $churchB))->assertForbidden();
    $this->actingAs($financeA)->post("/church/{$churchA->slug}/admin/giving/{$donationB->id}/refund", ['reason' => 'x'])->assertNotFound();
});

test('receipts are downloadable only by the giver and finance', function () {
    [$church, , $finance, $giver] = givingChurch();
    $donation = $church->donations()->create(['user_id' => $giver->id, 'category' => 'tithe', 'amount' => 1000, 'method' => 'cash', 'status' => 'pending']);
    app(DonationService::class)->markSucceeded($donation);

    $this->actingAs($giver)->get(route('giving.receipt', $donation->reference))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($finance)->get(route('giving.receipt', $donation->reference))->assertOk();
    $this->actingAs(member())->get(route('giving.receipt', $donation->reference))->assertForbidden();
});

test('bank statements auto-match pending transfers by reference', function () {
    Notification::fake();
    [$church, , $finance, $giver] = givingChurch();
    $donation = $church->donations()->create(['user_id' => $giver->id, 'category' => 'tithe', 'amount' => 75000, 'method' => 'bank', 'status' => 'pending']);
    $csv = "date,description,reference,amount\n".now()->toDateString().",Transfer from Grace,{$donation->reference},75000\n".now()->toDateString().",Unknown deposit,XYZ,12345\n";

    $this->actingAs($finance)->post(route('church-admin.reconciliation.import', $church), [
        'statement' => UploadedFile::fake()->createWithContent('statement.csv', $csv),
    ])->assertRedirect();

    expect($donation->fresh()->status)->toBe('succeeded')
        ->and(BankStatementLine::where('status', 'matched')->count())->toBe(1)
        ->and(BankStatementLine::where('status', 'unmatched')->count())->toBe(1);
});

test('paid event tickets are confirmed by payment', function () {
    [$church, , , $giver] = givingChurch();
    $event = $church->events()->create(['title' => 'Concert', 'starts_at' => now()->addWeek(), 'status' => 'published', 'visibility' => 'public', 'rsvp_enabled' => true, 'is_paid' => true]);
    $ticket = $event->tickets()->create(['church_id' => $church->id, 'name' => 'Adult', 'price' => 10000]);

    $this->actingAs($giver)->post(route('events.rsvp', $event), ['event_ticket_id' => $ticket->id, 'quantity' => 1])->assertRedirect();
    $rsvp = EventRsvp::firstOrFail();
    $this->actingAs($giver)->post(route('give.ticket', $rsvp), ['method' => 'mtn_momo', 'phone' => '0772123456'])->assertRedirect();

    $this->travel(5)->seconds();
    app(DonationService::class)->sync(Donation::firstOrFail());
    expect($rsvp->fresh()->status)->toBe('going')->and($ticket->fresh()->sold_count)->toBe(1);
});

test('USSD giving walks through the menu and starts a payment', function () {
    [$church, , , $giver] = givingChurch();
    $post = fn (string $text) => $this->post('/webhooks/ussd', ['sessionId' => 'S1', 'phoneNumber' => '+256772123456', 'text' => $text])->getContent();

    expect($post(''))->toStartWith('CON Welcome')
        ->and($post('1'))->toContain('church code')
        ->and($post('1*'.$church->id))->toContain('Tithe')
        ->and($post('1*'.$church->id.'*1'))->toContain('amount')
        ->and($post('1*'.$church->id.'*1*5000'))->toContain('Confirm')
        ->and($post('1*'.$church->id.'*1*5000*1'))->toStartWith('END You will receive');

    expect(Donation::where('note', 'USSD')->value('amount'))->toEqual(5000)
        ->and(Donation::where('note', 'USSD')->value('user_id'))->toBe($giver->id);
});

test('billing invoices after the free year and suspends after the grace period', function () {
    $this->seed(PlanSeeder::class);
    $church = Church::factory()->create();
    app(BillingService::class)->startTrial($church);
    $church->update(['trial_ends_at' => today()->addDays(3)]);

    expect(app(BillingService::class)->generateDueInvoices())->toBe(1)
        ->and(app(BillingService::class)->generateDueInvoices())->toBe(0);

    $invoice = SubscriptionInvoice::firstOrFail();
    expect((float) $invoice->total)->toBe((float) config('faithfellows.pricing.plan_price'));

    $this->travel(10 + config('faithfellows.pricing.grace_days') + 1)->days();
    app(BillingService::class)->enforce();
    expect($church->fresh()->status)->toBe('suspended')->and($invoice->fresh()->status)->toBe('overdue');

    app(BillingService::class)->markInvoicePaid($invoice->fresh(), 'MANUAL-1', 'bank');
    expect($church->fresh()->status)->toBe('active')->and($church->fresh()->billing_status)->toBe('active');
});

test('the member giving page lists gifts and pledges', function () {
    [$church, , , $giver] = givingChurch();
    $church->donations()->create(['user_id' => $giver->id, 'category' => 'tithe', 'amount' => 43210, 'method' => 'cash', 'status' => 'succeeded', 'paid_at' => now()]);

    $this->actingAs($giver)->get(route('giving.index'))->assertOk()->assertSee('43,210');
});
