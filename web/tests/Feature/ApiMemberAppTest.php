<?php

use App\Models\Church;
use App\Models\Donation;
use App\Models\Event;
use App\Models\RecurringGift;
use App\Models\User;
use App\Notifications\Auth\PasswordResetCode;
use App\Services\DonationService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/*
| What the app does without sending anyone to the website: reserve and pay for event tickets, read devotionals,
| announcements and campaigns, watch and chat on a live service, open prayer requests, give and read receipts,
| read the legal pages and reset a forgotten password.
*/

beforeEach(function () {
    PaymentService::flush();
    config(['faithfellows.payments.sandbox' => true]);
});

/** @return array{0: User, 1: Church} a signed-in member who follows a church with a mobile money channel */
function appMember(): array
{
    $member = member(['phone' => '0772123456']);
    Sanctum::actingAs($member, ['member']);
    $church = Church::factory()->create(['name' => 'Grace Lira', 'slug' => 'grace-lira']);
    $church->subscriptions()->create(['user_id' => $member->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);
    $church->givingChannels()->create(['type' => 'mtn_momo', 'is_active' => true]);

    return [$member, $church];
}

function appEvent(Church $church, array $attributes = []): Event
{
    return $church->events()->create(array_merge([
        'title' => 'Youth Conference', 'description' => '<p>A day of worship.</p>', 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(4),
        'status' => 'published', 'visibility' => 'public', 'rsvp_enabled' => true, 'capacity' => 100, 'qa_enabled' => true,
        'agenda' => [['time' => '09:00', 'title' => 'Worship']], 'speakers' => [['name' => 'Pastor Ruth']],
    ], $attributes));
}

// ── Events ──────────────────────────────────────────────────────────────────────────────────────────────

test('the event screen has everything needed to reserve a seat', function () {
    [, $church] = appMember();
    $event = appEvent($church);

    $this->getJson(route('api.v1.events.show', $event))->assertOk()
        ->assertJsonPath('data.title', 'Youth Conference')
        ->assertJsonPath('data.description', 'A day of worship.')
        ->assertJsonPath('data.seats_left', 100)
        ->assertJsonPath('data.going_count', 0)
        ->assertJsonPath('data.qa_enabled', true)
        ->assertJsonPath('data.agenda.0.title', 'Worship')
        ->assertJsonPath('data.speakers.0.name', 'Pastor Ruth')
        ->assertJsonPath('data.my_rsvp', null)
        ->assertJsonPath('data.is_past', false);
});

test('a free event is reserved, shows a ticket code, and can be cancelled', function () {
    [$member, $church] = appMember();
    $event = appEvent($church);

    $this->postJson(route('api.v1.events.rsvp', $event), ['quantity' => 2])->assertCreated()
        ->assertJsonPath('data.status', 'going')->assertJsonPath('data.quantity', 2)->assertJsonPath('next.action', 'done');

    $detail = $this->getJson(route('api.v1.events.show', $event))->assertOk()->assertJsonPath('data.seats_left', 98)->assertJsonPath('data.going_count', 2)->json('data');
    expect($detail['my_rsvp']['code'])->toHaveLength(10);

    $this->getJson(route('api.v1.tickets.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.event.title', 'Youth Conference');

    $this->deleteJson(route('api.v1.events.rsvp.cancel', $event))->assertOk()->assertJsonPath('status', 'cancelled');
    $this->getJson(route('api.v1.tickets.index'))->assertOk()->assertJsonCount(0, 'data');
});

test('a full event refuses more seats than are left and a past event refuses everyone', function () {
    [, $church] = appMember();
    $full = appEvent($church, ['title' => 'Small room', 'capacity' => 1]);
    $past = appEvent($church, ['title' => 'Last year', 'starts_at' => now()->subYear(), 'ends_at' => now()->subYear()->addHours(2)]);

    $this->postJson(route('api.v1.events.rsvp', $full), ['quantity' => 2])->assertUnprocessable()->assertJsonValidationErrors('quantity');
    $this->postJson(route('api.v1.events.rsvp', $past))->assertUnprocessable()->assertJsonValidationErrors('rsvp');
});

test('a paid event holds the seat until the ticket is paid for in the app', function () {
    [$member, $church] = appMember();
    $event = appEvent($church, ['title' => 'Gala dinner', 'is_paid' => true]);
    $ticket = $event->tickets()->create(['church_id' => $church->id, 'name' => 'Standard', 'price' => 20000, 'currency' => 'UGX', 'quantity' => 50]);

    // A paid event needs a ticket choice.
    $this->postJson(route('api.v1.events.rsvp', $event))->assertUnprocessable()->assertJsonValidationErrors('event_ticket_id');

    $rsvp = $this->postJson(route('api.v1.events.rsvp', $event), ['event_ticket_id' => $ticket->id, 'quantity' => 2])->assertCreated()
        ->assertJsonPath('data.status', 'pending_payment')->assertJsonPath('data.amount', 40000)->assertJsonPath('next.action', 'pay')->json('data');
    expect($rsvp['code'])->toBeNull(); // no ticket code until it is paid

    $this->postJson(route('api.v1.rsvps.pay', $rsvp['id']), ['method' => 'mtn_momo'])->assertUnprocessable()->assertJsonValidationErrors('phone');

    $paid = $this->postJson(route('api.v1.rsvps.pay', $rsvp['id']), ['method' => 'mtn_momo', 'phone' => '0772123456'])->assertCreated()
        ->assertJsonPath('data.amount', 40000)->assertJsonPath('data.category', 'event')->json();
    expect($paid['next']['poll_url'])->toContain('/giving/');

    // Someone else cannot pay for this reservation.
    $reference = $paid['data']['reference'];
    Sanctum::actingAs(member(), ['member']);
    $this->postJson(route('api.v1.rsvps.pay', $rsvp['id']), ['method' => 'mtn_momo', 'phone' => '0772123456'])->assertForbidden();
    $this->getJson(route('api.v1.giving.show', $reference))->assertNotFound();
});

test('questions can be sent to the speakers only when the event has Q and A', function () {
    [, $church] = appMember();
    $open = appEvent($church);
    $closed = appEvent($church, ['title' => 'No questions', 'qa_enabled' => false]);

    $this->postJson(route('api.v1.events.ask', $open), ['body' => 'Is there lunch?'])->assertCreated()->assertJsonPath('data.mine', true);
    $this->getJson(route('api.v1.events.show', $open))->assertJsonPath('data.questions.0.body', 'Is there lunch?');
    $this->postJson(route('api.v1.events.ask', $closed), ['body' => 'Hello?'])->assertNotFound();
});

// ── Devotionals, announcements, campaigns ─────────────────────────────────────────────────────────────────

test('devotionals are listed newest first and open as plain text', function () {
    [, $church] = appMember();
    $church->devotionals()->create(['title' => 'Yesterday', 'slug' => 'yesterday', 'body' => 'old', 'publish_date' => today()->subDay(), 'status' => 'published', 'visibility' => 'public']);
    $today = $church->devotionals()->create([
        'title' => 'Walk by faith', 'slug' => 'walk', 'scripture' => '2 Corinthians 5:7', 'scripture_text' => 'We walk by faith.',
        'body' => '<p>Trust Him.</p><p>Every day.</p>', 'prayer' => '<p>Lord, help me.</p>', 'publish_date' => today(), 'status' => 'published', 'visibility' => 'public',
    ]);
    $church->devotionals()->create(['title' => 'Tomorrow', 'slug' => 'tomorrow', 'body' => 'x', 'publish_date' => today()->addDay(), 'status' => 'published', 'visibility' => 'public']);

    expect($this->getJson(route('api.v1.devotionals.index'))->assertOk()->json('data.*.title'))->toBe(['Walk by faith', 'Yesterday']);

    $this->getJson(route('api.v1.devotionals.show', $today))->assertOk()
        ->assertJsonPath('data.body', "Trust Him.\n\nEvery day.")->assertJsonPath('data.prayer', 'Lord, help me.')->assertJsonPath('data.scripture', '2 Corinthians 5:7');
});

test('an announcement opens in full, and staff-only ones stay hidden', function () {
    [, $church] = appMember();
    $open = $church->announcements()->create(['title' => 'Road closed', 'body' => '<p>Use the back gate.</p>', 'audience' => 'members', 'priority' => 'urgent', 'status' => 'published', 'published_at' => now()]);
    $staff = $church->announcements()->create(['title' => 'Staff memo', 'body' => 'x', 'audience' => 'staff', 'priority' => 'normal', 'status' => 'published', 'published_at' => now()]);

    $this->getJson(route('api.v1.announcements.show', $open))->assertOk()->assertJsonPath('data.body', 'Use the back gate.')->assertJsonPath('data.urgent', true);
    expect($this->getJson(route('api.v1.announcements.index'))->json('data.*.title'))->toBe(['Road closed']);
    $this->getJson(route('api.v1.announcements.show', $staff))->assertForbidden();
});

test('a campaign shows its progress, milestones and encouragement', function () {
    [, $church] = appMember();
    $campaign = $church->campaigns()->create(['title' => 'Roof Fund', 'type' => 'building', 'short_description' => 'Fix the roof', 'story' => '<p>Rain comes in.</p>', 'goal_amount' => 1000000, 'raised_amount' => 250000, 'status' => 'active', 'visibility' => 'public']);
    $campaign->milestones()->create(['church_id' => $church->id, 'percent' => 25, 'title' => '25% funded', 'reached_at' => now()]);

    expect($this->getJson(route('api.v1.campaigns.index'))->assertOk()->json('data.0.title'))->toBe('Roof Fund');
    $this->getJson(route('api.v1.campaigns.show', $campaign))->assertOk()
        ->assertJsonPath('data.description', 'Rain comes in.')->assertJsonPath('data.percent', 25)->assertJsonPath('data.milestones.0.reached', true);

    $this->postJson(route('api.v1.campaigns.comment', $campaign), ['body' => 'God bless this work'])->assertCreated();
    $this->getJson(route('api.v1.campaigns.show', $campaign))->assertJsonPath('data.comments.0.body', 'God bless this work');
});

// ── Live ───────────────────────────────────────────────────────────────────────────────────────────────────

test('a live service gives the app its playback source, viewer count and chat', function () {
    [$member, $church] = appMember();
    $stream = $church->liveStreams()->create(['title' => 'Sunday Service', 'scheduled_at' => now(), 'source_type' => 'hls', 'external_url' => 'https://cdn.example.test/live.m3u8', 'status' => 'live', 'started_at' => now(), 'chat_enabled' => true]);

    $this->getJson(route('api.v1.live.show', $stream))->assertOk()
        ->assertJsonPath('data.status', 'live')->assertJsonPath('data.playback.video', 'https://cdn.example.test/live.m3u8')->assertJsonPath('data.chat_enabled', true);

    $this->postJson(route('api.v1.live.heartbeat', $stream))->assertOk()->assertJsonPath('viewers', 1);

    $this->postJson(route('api.v1.live.messages.store', $stream), ['body' => 'Amen! <b>bold</b>'])->assertCreated()->assertJsonPath('data.body', 'Amen! bold');
    $messages = $this->getJson(route('api.v1.live.messages', $stream))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.mine', true)->json();
    $this->getJson(route('api.v1.live.messages', [$stream, 'after' => $messages['data'][0]['id']]))->assertJsonCount(0, 'data');

    // Chat closes when the service ends, and the playback source goes away.
    $stream->update(['status' => 'ended', 'ended_at' => now()]);
    $this->postJson(route('api.v1.live.messages.store', $stream), ['body' => 'Hello'])->assertUnprocessable();
    $this->getJson(route('api.v1.live.show', $stream))->assertJsonPath('data.playback.video', null);
});

test('a suspended church\'s live service stays closed', function () {
    appMember();
    $other = Church::factory()->create(['status' => 'suspended']);
    $stream = $other->liveStreams()->create(['title' => 'Private', 'scheduled_at' => now(), 'source_type' => 'rtmp', 'status' => 'live']);

    $this->getJson(route('api.v1.live.show', $stream))->assertForbidden();
});

// ── Prayer ─────────────────────────────────────────────────────────────────────────────────────────────────

test('a member opens, answers and removes their own prayer request', function () {
    [$member, $church] = appMember();
    $mine = $church->prayerRequests()->create(['user_id' => $member->id, 'body' => 'Please pray for my exams', 'visibility' => 'church', 'status' => 'open']);
    $theirs = $church->prayerRequests()->create(['user_id' => member()->id, 'body' => 'Healing please', 'visibility' => 'church', 'status' => 'open']);

    $this->getJson(route('api.v1.prayer.show', $mine))->assertOk()->assertJsonPath('data.is_mine', true)->assertJsonPath('data.prayed', false);
    $this->postJson(route('api.v1.prayer.pray', $theirs))->assertOk()->assertJsonPath('prayed', true);
    $this->getJson(route('api.v1.prayer.show', $theirs))->assertJsonPath('data.prayed', true);

    $this->postJson(route('api.v1.prayer.answered', $theirs), ['answer_note' => 'x'])->assertForbidden();
    $this->postJson(route('api.v1.prayer.answered', $mine), ['answer_note' => 'Passed!'])->assertOk()->assertJsonPath('data.status', 'answered')->assertJsonPath('data.answer_note', 'Passed!');
    expect($this->getJson(route('api.v1.prayer.index', ['status' => 'mine']))->json('data'))->toHaveCount(1);

    $this->deleteJson(route('api.v1.prayer.destroy', $theirs))->assertForbidden();
    $this->deleteJson(route('api.v1.prayer.destroy', $mine))->assertOk();
    expect($this->getJson(route('api.v1.prayer.index', ['status' => 'mine']))->json('data'))->toHaveCount(0);
});

// ── Giving ─────────────────────────────────────────────────────────────────────────────────────────────────

test('the give screen lists the methods, categories and campaigns a church offers', function () {
    [, $church] = appMember();
    $church->givingChannels()->create(['type' => 'bank', 'is_active' => true, 'instructions' => 'Stanbic 123']);
    $church->campaigns()->create(['title' => 'Roof Fund', 'type' => 'building', 'goal_amount' => 1000, 'status' => 'active', 'visibility' => 'public']);

    $data = $this->getJson(route('api.v1.giving.options', $church->slug))->assertOk()->json('data');

    expect(collect($data['methods'])->pluck('type')->all())->toBe(['mtn_momo'])
        ->and($data['methods'][0]['needs_phone'])->toBeTrue()
        ->and($data['offline'][0]['instructions'])->toBe('Stanbic 123')
        ->and(collect($data['categories'])->pluck('key')->all())->toContain('tithe', 'offering')->not->toContain('event')
        ->and($data['campaigns'][0]['title'])->toBe('Roof Fund')
        ->and($data['saved_phone'])->toBe('0772123456')
        ->and($data['diaspora'])->toBeFalse();
});

test('a gift can repeat, and its receipt is read in the app once paid', function () {
    [$member, $church] = appMember();

    $response = $this->postJson(route('api.v1.giving.store', $church->slug), [
        'amount' => 10000, 'category' => 'tithe', 'method' => 'mtn_momo', 'phone' => '0772123456', 'recurring' => 'monthly',
    ])->assertCreated();
    $reference = $response->json('data.reference');

    expect(RecurringGift::where('user_id', $member->id)->count())->toBe(1);
    $recurring = $this->getJson(route('api.v1.giving.summary'))->assertOk()->json('data.recurring.0');
    expect($recurring['frequency'])->toBe('monthly')->and($recurring['church']['slug'])->toBe('grace-lira');

    $this->putJson(route('api.v1.recurring.update', $recurring['id']), ['status' => 'paused'])->assertOk()->assertJsonPath('data.status', 'paused');

    // No receipt until the payment has succeeded.
    $this->getJson(route('api.v1.giving.receipt', $reference))->assertNotFound();
    $donation = Donation::where('reference', $reference)->firstOrFail();
    app(DonationService::class)->markSucceeded($donation);

    $this->getJson(route('api.v1.giving.receipt', $reference))->assertOk()
        ->assertJsonPath('data.amount', 10000)->assertJsonPath('data.church.name', 'Grace Lira')->assertJsonPath('data.giver', $member->name);
    $this->getJson(route('api.v1.giving.summary'))->assertJsonPath('data.year_total', 10000);
});

test('a member can pledge to a campaign and cancel the pledge', function () {
    [, $church] = appMember();
    $campaign = $church->campaigns()->create(['title' => 'Roof Fund', 'type' => 'building', 'goal_amount' => 1000000, 'status' => 'active', 'visibility' => 'public']);

    $pledge = $this->postJson(route('api.v1.pledges.store', $church->slug), ['amount' => 50000, 'frequency' => 'monthly', 'installments' => 5, 'campaign_id' => $campaign->id])
        ->assertCreated()->assertJsonPath('data.campaign.title', 'Roof Fund')->json('data');
    $this->getJson(route('api.v1.giving.summary'))->assertJsonCount(1, 'data.pledges');

    $this->deleteJson(route('api.v1.pledges.cancel', $pledge['id']))->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->getJson(route('api.v1.giving.summary'))->assertJsonCount(0, 'data.pledges');

    Sanctum::actingAs(member(), ['member']);
    $this->deleteJson(route('api.v1.pledges.cancel', $pledge['id']))->assertForbidden();
});

// ── Legal pages and password reset ────────────────────────────────────────────────────────────────────────

test('the terms and privacy pages are readable in the app without signing in', function () {
    foreach (['terms', 'privacy'] as $page) {
        $data = $this->getJson(route('api.v1.pages.show', $page))->assertOk()->json('data');
        expect($data['title'])->not->toBeEmpty()
            ->and($data['blocks'])->not->toBeEmpty()
            ->and(collect($data['blocks'])->pluck('type')->unique()->all())->toContain('heading', 'paragraph');
    }
    $this->getJson(route('api.v1.pages.show', 'nonsense'))->assertNotFound();
});

test('a forgotten password is reset with a six-digit code sent by email', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'mary@example.test', 'password' => Hash::make('old-password-1')]);
    $user->createToken('phone');

    // The answer is the same for an unknown address.
    $this->postJson(route('api.v1.auth.password.code'), ['email' => 'nobody@example.test'])->assertAccepted();
    Notification::assertNothingSent();

    $this->postJson(route('api.v1.auth.password.code'), ['email' => 'Mary@Example.test'])->assertAccepted();
    $code = null;
    Notification::assertSentTo($user, PasswordResetCode::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });

    $new = ['email' => 'mary@example.test', 'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9'];
    $this->postJson(route('api.v1.auth.password.reset'), $new + ['code' => $code === '123456' ? '654321' : '123456'])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson(route('api.v1.auth.password.reset'), $new + ['code' => $code])->assertOk();

    expect(Hash::check('brand-new-pass-9', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
    $this->postJson(route('api.v1.auth.token'), ['email' => 'mary@example.test', 'password' => 'brand-new-pass-9', 'device_name' => 'phone'])->assertCreated();

    // A code works once.
    $this->postJson(route('api.v1.auth.password.reset'), $new + ['code' => $code])->assertUnprocessable();
});

test('the reset code locks after five wrong tries', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'peter@example.test']);
    $this->postJson(route('api.v1.auth.password.code'), ['email' => 'peter@example.test'])->assertAccepted();
    $code = null;
    Notification::assertSentTo($user, PasswordResetCode::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });
    $wrong = $code === '111111' ? '222222' : '111111';
    $new = ['email' => 'peter@example.test', 'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9'];

    foreach (range(1, 5) as $_) {
        $this->postJson(route('api.v1.auth.password.reset'), $new + ['code' => $wrong])->assertUnprocessable();
    }
    // Even the right code no longer works: ask for a new one.
    $this->postJson(route('api.v1.auth.password.reset'), $new + ['code' => $code])->assertUnprocessable();
});
