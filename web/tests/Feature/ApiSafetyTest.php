<?php

use App\Models\Church;
use App\Models\Connection;
use App\Models\DirectMessage;
use App\Models\User;
use App\Support\ContactGuard;
use Laravel\Sanctum\Sanctum;

/*
| Keeping conversations inside the app: no phone numbers, emails or chat links between members, and one message to
| someone until they reply.
*/

function safeChurch(): Church
{
    return Church::factory()->create();
}

function safeMember(Church $church, array $attributes = []): User
{
    $user = member(array_merge(['date_of_birth' => now()->subYears(30)->toDateString(), 'directory_opt_in' => true], $attributes));
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);

    return $user;
}

function connected(User $a, User $b): void
{
    Connection::create(['requester_id' => $a->id, 'addressee_id' => $b->id, 'status' => 'accepted', 'responded_at' => now()]);
}

dataset('contact details', [
    'local number' => '0777676206',
    'spaced local number' => 'call me on 0772 123 456 please',
    'dashed number' => '077-212-3456',
    'international' => 'My number is +256 772 123 456',
    'american' => '+1 (555) 123-4567',
    'one digit at a time' => '0 7 7 2 1 2 3 4 5 6',
    'dotted' => '0772.123.456',
    'in words' => 'zero seven seven two one two three four five six',
    'email' => 'write to grace@example.com',
    'email spelled' => 'grace [at] gmail [dot] com',
    'whatsapp link' => 'chat me https://wa.me/256772123456',
    'telegram link' => 't.me/gracelira',
    'arabic digits' => '٠٧٧٢١٢٣٤٥٦',
    'full width digits' => '０７７２１２３４５６',
]);

dataset('ordinary words', [
    'a verse' => 'John 3:16 is my favourite verse, and Psalm 119:105-112 too',
    'a date' => 'Let us meet on 13-10-2026 at 10:30',
    'an iso date' => 'Posted on 2026-10-13',
    'an amount' => 'We raised 25,000,000 shillings for the roof',
    'a small amount' => 'Please bring 5000 for lunch',
    'some numbers in words' => 'one two three four five, God is good',
    'a time' => 'Service starts at 9:00 and ends at 12:30',
    'a year' => 'Founded in 1998 and rebuilt in 2015',
]);

test('phone numbers, emails and chat links are spotted however they are written', function (string $text) {
    expect(ContactGuard::found($text))->toBeTrue();
})->with('contact details');

test('everyday words, dates, amounts and verses are left alone', function (string $text) {
    expect(ContactGuard::found($text))->toBeFalse();
})->with('ordinary words');

test('a message with a phone number is refused, and nothing is saved', function () {
    $church = safeChurch();
    $ann = safeMember($church);
    $bob = safeMember($church);
    connected($ann, $bob);
    Sanctum::actingAs($ann, ['member']);

    $this->postJson(route('api.v1.messages.send', $bob), ['body' => 'Call me on 0777676206'])->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->postJson(route('api.v1.messages.send', $bob), ['body' => 'Email me: ann@example.com'])->assertUnprocessable();
    expect(DirectMessage::count())->toBe(0);

    $this->postJson(route('api.v1.messages.send', $bob), ['body' => 'Peace be with you'])->assertCreated();
});

test('after one message you wait; once they answer both of you can write as much as you like', function () {
    $church = safeChurch();
    $ann = safeMember($church, ['name' => 'Ann Akello']);
    $bob = safeMember($church, ['name' => 'Bob Okello']);
    connected($ann, $bob);

    Sanctum::actingAs($ann, ['member']);
    expect($this->getJson(route('api.v1.messages.thread', $bob))->json('partner.waiting_for_reply'))->toBeFalse();
    $this->postJson(route('api.v1.messages.send', $bob), ['body' => 'Hello Bob'])->assertCreated();

    $this->postJson(route('api.v1.messages.send', $bob), ['body' => 'Are you there?'])->assertUnprocessable()->assertJsonPath('errors.body.0', 'Wait for Bob to reply before you send another message.');
    expect($this->getJson(route('api.v1.messages.thread', $bob))->json('partner.waiting_for_reply'))->toBeTrue()
        ->and(DirectMessage::count())->toBe(1);

    // Bob is free to answer, and his answer opens the chat for both.
    Sanctum::actingAs($bob, ['member']);
    expect($this->getJson(route('api.v1.messages.thread', $ann))->json('partner.waiting_for_reply'))->toBeFalse();
    $this->postJson(route('api.v1.messages.send', $ann), ['body' => 'Hi Ann, I am here'])->assertCreated();

    Sanctum::actingAs($ann, ['member']);
    foreach (['Great to hear from you', 'How is the family?', 'Let us pray for each other'] as $line) {
        $this->postJson(route('api.v1.messages.send', $bob), ['body' => $line])->assertCreated();
    }
    expect(DirectMessage::count())->toBe(5);
});

test('connection messages, bios, group posts and comments also keep contact details out', function () {
    $church = safeChurch();
    $ann = safeMember($church);
    $bob = safeMember($church);
    Sanctum::actingAs($ann, ['member']);

    $this->postJson(route('api.v1.people.connect', $bob), ['message' => 'Hi, WhatsApp me on 0777676206'])->assertUnprocessable()->assertJsonValidationErrors('message');
    $this->patchJson(route('api.v1.me.update'), ['bio' => 'Reach me at ann@example.com'])->assertUnprocessable()->assertJsonValidationErrors('bio');
    $this->patchJson(route('api.v1.me.update'), ['bio' => 'I love worship and Bible study in Lira'])->assertOk();
});
