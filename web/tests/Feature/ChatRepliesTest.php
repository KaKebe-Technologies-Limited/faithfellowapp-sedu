<?php

use App\Models\Church;
use App\Models\Connection;
use App\Models\SmallGroupMember;
use Laravel\Sanctum\Sanctum;

/*
| Swipe a message to reply: a message can answer an earlier one, in a private chat and in a group's conversation. The answer carries a short quote of the
| message it answers, and only a message of the same conversation can be answered.
*/

function replyAdult(string $name)
{
    return member(['name' => $name, 'date_of_birth' => now()->subYears(30)->toDateString(), 'directory_opt_in' => true]);
}

test('a private message can answer an earlier one and shows a quote of it to both people', function () {
    $ann = replyAdult('Ann');
    $ben = replyAdult('Ben');
    Connection::create(['requester_id' => $ann->id, 'addressee_id' => $ben->id, 'status' => 'accepted', 'responded_at' => now()]);

    Sanctum::actingAs($ann, ['member']);
    $first = $this->postJson(route('api.v1.messages.send', $ben), ['body' => str_repeat('Come to the choir practice on Thursday evening. ', 8)])->assertCreated()->json('data');
    expect($first)->not->toHaveKey('reply_to');

    Sanctum::actingAs($ben, ['member']);
    $answer = $this->postJson(route('api.v1.messages.send', $ann), ['body' => 'I will be there', 'reply_to' => $first['id']])->assertCreated()->json('data');
    expect($answer['reply_to']['id'])->toBe($first['id'])->and($answer['reply_to']['mine'])->toBeFalse()->and(mb_strlen($answer['reply_to']['body']))->toBeLessThanOrEqual(143) // 140 characters and an ellipsis
        ->and($answer['reply_to']['body'])->toStartWith('Come to the choir practice');

    // Ann sees her own message quoted, marked as hers.
    Sanctum::actingAs($ann, ['member']);
    $thread = $this->getJson(route('api.v1.messages.thread', $ben))->assertOk()->json('data');
    expect($thread[1]['reply_to']['mine'])->toBeTrue()->and($thread[0])->not->toHaveKey('reply_to');

    // Answering in a message of someone else's chat is refused, and a message without a reply is unchanged.
    $cat = replyAdult('Cat');
    Connection::create(['requester_id' => $ann->id, 'addressee_id' => $cat->id, 'status' => 'accepted', 'responded_at' => now()]);
    $this->postJson(route('api.v1.messages.send', $cat), ['body' => 'Different chat', 'reply_to' => $first['id']])->assertUnprocessable()->assertJsonValidationErrors('reply_to');
    $this->postJson(route('api.v1.messages.send', $cat), ['body' => 'Hello Cat'])->assertCreated()->assertJsonMissingPath('data.reply_to');
});

test('a message in a group can answer another and names who wrote it', function () {
    $church = Church::factory()->create();
    $leader = replyAdult('Leader Lucy');
    $group = $church->smallGroups()->create(['name' => 'Romans Study', 'type' => 'bible_study', 'leader_id' => $leader->id, 'is_open' => true]);
    $other = $church->smallGroups()->create(['name' => 'Elsewhere', 'type' => 'bible_study', 'leader_id' => $leader->id, 'is_open' => true]);
    $ann = replyAdult('Ann');
    $ben = replyAdult('Ben');
    foreach ([$leader, $ann, $ben] as $user) {
        $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);
    }
    foreach ([$ann, $ben] as $user) {
        SmallGroupMember::create(['church_id' => $church->id, 'small_group_id' => $group->id, 'user_id' => $user->id, 'role' => 'member', 'status' => 'active', 'joined_at' => now()]);
    }
    SmallGroupMember::create(['church_id' => $church->id, 'small_group_id' => $other->id, 'user_id' => $ann->id, 'role' => 'member', 'status' => 'active', 'joined_at' => now()]);

    Sanctum::actingAs($ann, ['member']);
    $question = $this->postJson(route('api.v1.groups.posts.store', $group), ['body' => 'Which chapter do we read on Wednesday?'])->assertCreated()->json('data');
    $elsewhere = $this->postJson(route('api.v1.groups.posts.store', $other), ['body' => 'A message of another group'])->assertCreated()->json('data');

    Sanctum::actingAs($ben, ['member']);
    $answer = $this->postJson(route('api.v1.groups.posts.store', $group), ['body' => 'Romans 8', 'reply_to' => $question['id']])->assertCreated()->json('data');
    expect($answer['reply_to'])->toMatchArray(['id' => $question['id'], 'body' => 'Which chapter do we read on Wednesday?', 'author' => 'Ann', 'mine' => false]);
    $this->postJson(route('api.v1.groups.posts.store', $group), ['body' => 'Wrong group', 'reply_to' => $elsewhere['id']])->assertUnprocessable()->assertJsonValidationErrors('reply_to');

    Sanctum::actingAs($ann, ['member']);
    $posts = collect($this->getJson(route('api.v1.groups.posts', $group))->assertOk()->json('data'));
    expect($posts->last()['reply_to']['mine'])->toBeTrue()->and($posts->first())->not->toHaveKey('reply_to');

    // The message that was answered can be deleted; the answer still says what it was about.
    $this->deleteJson(route('api.v1.groups.posts.destroy', [$group, $question['id']]))->assertOk();
    $after = collect($this->getJson(route('api.v1.groups.posts', $group))->json('data'));
    expect($after->last()['reply_to']['body'])->toBe('This message was deleted.');
});
