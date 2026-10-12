<?php

use App\Models\Church;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\LiveChatMessage;
use App\Models\User;
use App\Notifications\Events\EventReminder;
use App\Notifications\Live\LiveStreamStarted;
use App\Services\EventService;
use App\Services\StreamingService;
use Illuminate\Support\Facades\Notification;

/** @return array{0: Church, 1: User, 2: User} */
function eventsChurch(): array
{
    $admin = member();
    $church = Church::factory()->create();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);
    $subscriber = member();
    $church->subscriptions()->create(['user_id' => $subscriber->id, 'status' => 'active', 'subscribed_at' => now()]);

    return [$church, $admin, $subscriber];
}

function makeEvent(Church $church, array $attributes = []): Event
{
    return $church->events()->create(array_merge([
        'title' => 'Youth Night', 'starts_at' => now()->addDays(3), 'status' => 'published', 'visibility' => 'public', 'rsvp_enabled' => true,
    ], $attributes));
}

test('admins can create events with tickets', function () {
    [$church, $admin] = eventsChurch();

    $this->actingAs($admin)->post(route('church-admin.events.store', $church), [
        'title' => 'Youth Camp', 'starts_at' => now()->addWeek()->format('Y-m-d H:i'), 'status' => 'published', 'visibility' => 'public',
        'rsvp_enabled' => 1, 'is_paid' => 1, 'capacity' => 100,
        'tickets' => [['name' => 'Camper', 'price' => 50000, 'quantity' => 80]],
        'agenda' => [['time' => '09:00', 'title' => 'Worship', 'speaker' => '']],
    ])->assertRedirect();

    $event = Event::firstOrFail();
    expect($event->tickets)->toHaveCount(1)->and($event->agenda[0]['title'])->toBe('Worship');
});

test('free RSVP confirms and respects capacity', function () {
    [$church, , $subscriber] = eventsChurch();
    $event = makeEvent($church, ['capacity' => 3]);

    $this->actingAs($subscriber)->post(route('events.rsvp', $event), ['quantity' => 2])->assertRedirect();
    expect(EventRsvp::first()->status)->toBe('going')->and(EventRsvp::first()->code)->toHaveLength(10);

    $other = member();
    $this->actingAs($other)->post(route('events.rsvp', $event), ['quantity' => 2])->assertSessionHasErrors('quantity');
    $this->actingAs($other)->post(route('events.rsvp', $event), ['quantity' => 1])->assertSessionHasNoErrors();
});

test('paid tickets reserve seats pending payment', function () {
    [$church, , $subscriber] = eventsChurch();
    $event = makeEvent($church, ['is_paid' => true]);
    $ticket = $event->tickets()->create(['church_id' => $church->id, 'name' => 'Adult', 'price' => 20000, 'quantity' => 10]);

    $this->actingAs($subscriber)->post(route('events.rsvp', $event), ['event_ticket_id' => $ticket->id, 'quantity' => 2]);

    $rsvp = EventRsvp::firstOrFail();
    expect($rsvp->status)->toBe('pending_payment')->and((float) $rsvp->amount)->toBe(40000.0);

    app(EventService::class)->confirmPaidRsvp($rsvp);
    expect($rsvp->fresh()->status)->toBe('going')->and($ticket->fresh()->sold_count)->toBe(2);
});

test('QR check-in works once and only for going tickets', function () {
    [$church, $admin, $subscriber] = eventsChurch();
    $event = makeEvent($church);
    $rsvp = $event->rsvps()->create(['church_id' => $church->id, 'user_id' => $subscriber->id, 'status' => 'going']);

    $this->actingAs($admin)->get(route('church-admin.events.check-in.code', [$church, $event, $rsvp->code]))
        ->assertRedirect()->assertSessionHas('checkin', fn ($r) => $r['ok'] === true);
    expect($rsvp->fresh()->checked_in_at)->not->toBeNull();

    $this->actingAs($admin)->post(route('church-admin.events.check-in.store', [$church, $event]), ['code' => $rsvp->code])
        ->assertSessionHas('checkin', fn ($r) => $r['ok'] === false);
});

test('members cannot use the door check-in endpoint', function () {
    [$church, , $subscriber] = eventsChurch();
    $event = makeEvent($church);
    $rsvp = $event->rsvps()->create(['church_id' => $church->id, 'user_id' => $subscriber->id, 'status' => 'going']);

    $this->actingAs($subscriber)->get(route('church-admin.events.check-in.code', [$church, $event, $rsvp->code]))->assertForbidden();
});

test('members can view their ticket with a QR code', function () {
    [$church, , $subscriber] = eventsChurch();
    $event = makeEvent($church);
    $event->rsvps()->create(['church_id' => $church->id, 'user_id' => $subscriber->id, 'status' => 'going']);

    $this->actingAs($subscriber)->get(route('events.ticket', $event))->assertOk()->assertSee('<svg', false);
});

test('reminders go out 24h and 1h before, once each', function () {
    Notification::fake();
    [$church, , $subscriber] = eventsChurch();
    $event = makeEvent($church, ['starts_at' => now()->addHours(23)->addMinutes(30)]);
    $event->rsvps()->create(['church_id' => $church->id, 'user_id' => $subscriber->id, 'status' => 'going']);

    $this->artisan('ff:event-reminders');
    $this->artisan('ff:event-reminders');
    Notification::assertSentToTimes($subscriber, EventReminder::class, 1);

    $this->travel(23)->hours();
    $this->artisan('ff:event-reminders');
    Notification::assertSentToTimes($subscriber, EventReminder::class, 2);
});

test('going live notifies subscribers once and tracks viewers', function () {
    Notification::fake();
    [$church, $admin, $subscriber] = eventsChurch();
    $stream = $church->liveStreams()->create(['title' => 'Sunday Service', 'scheduled_at' => now(), 'source_type' => 'rtmp']);
    app(StreamingService::class)->rotateKey($stream);

    $this->actingAs($admin)->post(route('church-admin.live.start', [$church, $stream]))->assertRedirect();
    $this->actingAs($admin)->post(route('church-admin.live.start', [$church, $stream]));

    expect($stream->fresh()->status)->toBe('live');
    Notification::assertSentToTimes($subscriber, LiveStreamStarted::class, 1);

    $this->actingAs($subscriber)->postJson(route('live.heartbeat', $stream))->assertJson(['viewers' => 1]);
    expect($stream->fresh()->peak_viewers)->toBe(1);
});

test('RTMP publish callback starts the right stream and rejects bad keys', function () {
    Notification::fake();
    [$church] = eventsChurch();
    $stream = $church->liveStreams()->create(['title' => 'Service', 'scheduled_at' => now(), 'source_type' => 'rtmp']);
    $key = app(StreamingService::class)->rotateKey($stream);

    $this->post('/webhooks/rtmp/publish', ['name' => 'nope'])->assertForbidden();
    $this->post('/webhooks/rtmp/publish', ['name' => $key])->assertOk();
    expect($stream->fresh()->status)->toBe('live');

    $this->post('/webhooks/rtmp/done', ['name' => $key])->assertOk();
    expect($stream->fresh()->status)->toBe('ended')->and($stream->fresh()->recording_url)->toContain($key);
});

test('stream keys are encrypted at rest', function () {
    [$church] = eventsChurch();
    $stream = $church->liveStreams()->create(['title' => 'S', 'scheduled_at' => now(), 'source_type' => 'rtmp']);
    $key = app(StreamingService::class)->rotateKey($stream);

    $raw = DB::table('live_streams')->where('id', $stream->id)->value('stream_key');
    expect($raw)->not->toBe($key)->and($stream->fresh()->stream_key)->toBe($key);
});

test('ended streams can be archived as sermons', function () {
    [$church, $admin] = eventsChurch();
    $stream = $church->liveStreams()->create(['title' => 'Service', 'scheduled_at' => now(), 'source_type' => 'youtube', 'external_url' => 'https://youtu.be/dQw4w9WgXcQ', 'status' => 'ended', 'started_at' => now()->subHours(2), 'ended_at' => now()]);

    $this->actingAs($admin)->post(route('church-admin.live.archive', [$church, $stream]), ['recording_url' => 'https://youtu.be/dQw4w9WgXcQ', 'preacher' => 'Rev. Okello'])->assertRedirect();

    expect($stream->fresh()->status)->toBe('archived')
        ->and($stream->fresh()->sermon->preacher)->toBe('Rev. Okello')
        ->and($stream->fresh()->sermon->duration_seconds)->toBe(7200);
});

test('live chat requires sign-in and respects moderation', function () {
    [$church, $admin, $subscriber] = eventsChurch();
    $stream = $church->liveStreams()->create(['title' => 'S', 'scheduled_at' => now(), 'source_type' => 'rtmp', 'status' => 'live', 'chat_enabled' => true]);

    $this->postJson(route('live.messages.store', $stream), ['body' => 'Hi'])->assertUnauthorized();
    $this->actingAs($subscriber)->postJson(route('live.messages.store', $stream), ['body' => 'Amen! <b>bold</b>'])->assertOk();

    $message = LiveChatMessage::firstOrFail();
    expect($message->body)->toBe('Amen! bold');

    $this->actingAs($admin)->postJson(route('church-admin.live.messages.hide', [$church, $stream, $message->id]))->assertOk();
    $this->getJson(route('live.messages', $stream))->assertJsonCount(0, 'messages');
});
