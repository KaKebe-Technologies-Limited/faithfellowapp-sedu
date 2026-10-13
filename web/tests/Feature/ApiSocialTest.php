<?php

use App\Models\Church;
use App\Models\Connection;
use App\Models\Quiz;
use App\Models\User;
use App\Services\QuizService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

/*
| People of the same faith in the app: quiz games, study groups with their conversation, finding other members,
| connecting, chatting, and the profile that controls who can find you.
*/

function socialChurch(string $name = 'Grace Lira'): Church
{
    return Church::factory()->create(['name' => $name, 'slug' => str($name)->slug()->toString()]);
}

/** A member of the church; adults who are findable by default. */
function socialMember(Church $church, array $attributes = []): User
{
    $user = member(array_merge(['date_of_birth' => now()->subYears(30)->toDateString(), 'directory_opt_in' => true, 'city' => 'Lira', 'country' => 'UG'], $attributes));
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);

    return $user;
}

function actingMember(User $user): User
{
    Sanctum::actingAs($user, ['member']);

    return $user;
}

// ── Quiz games ──────────────────────────────────────────────────────────────────────────────────────────

test('a member joins a live quiz with its PIN, answers, and sees the standings', function () {
    $church = socialChurch();
    $host = socialMember($church);
    $ann = actingMember(socialMember($church, ['name' => 'Ann Player']));
    $bob = socialMember($church, ['name' => 'Bob Player']);

    $quiz = Quiz::create(['church_id' => $church->id, 'user_id' => $host->id, 'title' => 'Bible Basics']);
    $quiz->questions()->create(['position' => 0, 'question' => 'Who built the ark?', 'options' => ['Moses', 'Noah', 'Abraham', 'David'], 'correct_option' => 1, 'time_limit' => 10, 'points' => 1000]);
    $session = app(QuizService::class)->host($quiz, $host);

    $list = $this->getJson(route('api.v1.games.index'))->assertOk()->json('data');
    expect($list['live'])->toHaveCount(1)->and($list['live'][0]['quiz'])->toBe('Bible Basics')->and($list['live'][0])->not->toHaveKey('pin');

    $this->postJson(route('api.v1.games.join'), ['pin' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('pin');
    $this->postJson(route('api.v1.games.join'), ['pin' => $session->pin])->assertOk()->assertJsonPath('data.id', $session->id);

    expect($this->getJson(route('api.v1.games.state', $session))->assertOk()->json('data'))->toMatchArray(['status' => 'lobby', 'total' => 1, 'players_count' => 1]);

    app(QuizService::class)->join($bob, $session);
    app(QuizService::class)->start($session);
    $state = $this->getJson(route('api.v1.games.state', $session))->json('data');
    expect($state['status'])->toBe('question')->and($state['question']['options'])->toBe(['Moses', 'Noah', 'Abraham', 'David'])->and($state)->not->toHaveKey('reveal');

    $this->postJson(route('api.v1.games.answer', $session), ['option' => 1, 'question' => 0])->assertOk()->assertJsonPath('locked', true);
    $this->postJson(route('api.v1.games.answer', $session), ['option' => 9, 'question' => 0])->assertUnprocessable();

    app(QuizService::class)->reveal($session);
    $after = $this->getJson(route('api.v1.games.state', $session))->json('data');
    expect($after['status'])->toBe('reveal')->and($after['reveal']['correct'])->toBe(1)->and($after['me']['correct'])->toBeTrue()->and($after['me']['rank'])->toBe(1)
        ->and($after['leaderboard'][0]['name'] ?? $after['leaderboard'][0]['nickname'] ?? null)->toBe('Ann Player');
});

test('a quiz of another church is not reachable', function () {
    $mine = socialChurch();
    $other = socialChurch('Elsewhere Church');
    $host = socialMember($other);
    actingMember(socialMember($mine));
    $quiz = Quiz::create(['church_id' => $other->id, 'user_id' => $host->id, 'title' => 'Private']);
    $quiz->questions()->create(['position' => 0, 'question' => 'Q', 'options' => ['a', 'b'], 'correct_option' => 0, 'time_limit' => 10, 'points' => 1000]);
    $session = app(QuizService::class)->host($quiz, $host);

    $this->getJson(route('api.v1.games.index'))->assertJsonCount(0, 'data.live');
    $this->postJson(route('api.v1.games.join'), ['pin' => $session->pin])->assertUnprocessable();
    $this->getJson(route('api.v1.games.state', $session))->assertNotFound();
});

// ── Study groups ────────────────────────────────────────────────────────────────────────────────────────

test('a member finds a group, asks to join, is approved by the leader, and takes part in its conversation', function () {
    $church = socialChurch();
    $leader = socialMember($church, ['name' => 'Leader Lucy']);
    $group = $church->smallGroups()->create(['name' => 'Romans Study', 'type' => 'bible_study', 'description' => 'Walking through Romans', 'leader_id' => $leader->id, 'meeting_day' => 3, 'meeting_time' => '18:00', 'is_open' => true]);
    $closed = $church->smallGroups()->create(['name' => 'Elders', 'type' => 'ministry', 'leader_id' => $leader->id, 'is_open' => false]);
    $member = actingMember(socialMember($church, ['name' => 'Newcomer Nick']));

    $list = $this->getJson(route('api.v1.groups.index'))->assertOk()->json('data');
    expect(collect($list['discover'])->pluck('name')->all())->toBe(['Romans Study'])->and($list['discover'][0]['cover_url'])->toContain('/art/group/')->and($list['discover'][0]['my_status'])->toBeNull();

    $this->postJson(route('api.v1.groups.join', $closed))->assertUnprocessable();
    $this->postJson(route('api.v1.groups.join', $group))->assertCreated()->assertJsonPath('status', 'pending');
    expect($this->getJson(route('api.v1.groups.index'))->json('data.pending.0.name'))->toBe('Romans Study');

    // Not a member yet: the conversation is closed and the notices are hidden.
    $this->getJson(route('api.v1.groups.posts', $group))->assertForbidden();
    expect($this->getJson(route('api.v1.groups.show', $group))->assertOk()->json('data.is_member'))->toBeFalse();

    // The leader sees the request and approves it.
    actingMember($leader);
    $pending = $this->getJson(route('api.v1.groups.show', $group))->json('data.pending_members');
    expect($pending)->toHaveCount(1)->and($pending[0]['name'])->toBe('Newcomer Nick');
    $this->putJson(route('api.v1.groups.members.update', [$group, $pending[0]['membership_id']]), ['action' => 'approve'])->assertOk();

    actingMember($member);
    expect($this->getJson(route('api.v1.groups.index'))->json('data.mine.0.my_role'))->toBe('member');
    $post = $this->postJson(route('api.v1.groups.posts.store', $group), ['body' => 'Hello <b>everyone</b>!'])->assertCreated()->assertJsonPath('data.body', 'Hello everyone!')->assertJsonPath('data.mine', true)->json('data');
    actingMember($leader);
    $this->postJson(route('api.v1.groups.posts.store', $group), ['body' => 'Welcome Nick'])->assertCreated();
    expect($this->getJson(route('api.v1.groups.posts', $group))->json('data.*.body'))->toBe(['Hello everyone!', 'Welcome Nick']);
    expect($this->getJson(route('api.v1.groups.posts', [$group, 'after' => $post['id']]))->json('data.*.body'))->toBe(['Welcome Nick']);

    // Only the author or a leader removes a message.
    actingMember(socialMember($church));
    $this->deleteJson(route('api.v1.groups.posts.destroy', [$group, $post['id']]))->assertForbidden();
    actingMember($leader);
    $this->deleteJson(route('api.v1.groups.posts.destroy', [$group, $post['id']]))->assertOk();

    // Leaving takes you out of the conversation.
    actingMember($member);
    $this->deleteJson(route('api.v1.groups.leave', $group))->assertOk();
    $this->getJson(route('api.v1.groups.posts', $group))->assertForbidden();
});

test('group leaders take attendance, post notices and add people; members cannot', function () {
    $church = socialChurch();
    $leader = socialMember($church);
    $other = socialMember($church, ['name' => 'Candidate Cathy']);
    $group = $church->smallGroups()->create(['name' => 'Cell One', 'type' => 'cell', 'leader_id' => $leader->id]);
    $group->memberships()->create(['church_id' => $church->id, 'user_id' => $leader->id, 'role' => 'leader', 'status' => 'active', 'joined_at' => now()]);
    $plain = socialMember($church);
    $group->memberships()->create(['church_id' => $church->id, 'user_id' => $plain->id, 'role' => 'member', 'status' => 'active', 'joined_at' => now()]);

    actingMember($plain);
    $this->postJson(route('api.v1.groups.meetings.store', $group), ['meeting_date' => today()->toDateString()])->assertForbidden();
    $this->postJson(route('api.v1.groups.announcements.store', $group), ['title' => 'x', 'body' => 'y'])->assertForbidden();
    $this->getJson(route('api.v1.groups.candidates', $group))->assertForbidden();

    actingMember($leader);
    expect($this->getJson(route('api.v1.groups.candidates', [$group, 'q' => 'Cathy']))->json('data.0.name'))->toBe('Candidate Cathy');
    $this->postJson(route('api.v1.groups.members.add', $group), ['user_id' => $other->id])->assertCreated();
    $this->postJson(route('api.v1.groups.meetings.store', $group), ['meeting_date' => today()->toDateString(), 'topic' => 'Romans 8', 'visitors' => 2, 'present' => [$leader->id, $plain->id]])->assertCreated();
    $this->postJson(route('api.v1.groups.announcements.store', $group), ['title' => 'Bring a friend', 'body' => 'Next week is open night.'])->assertCreated();

    $show = $this->getJson(route('api.v1.groups.show', $group))->json('data');
    expect($show['members'])->toHaveCount(3)->and($show['meetings'][0])->toMatchArray(['topic' => 'Romans 8', 'present' => 2, 'visitors' => 2])
        ->and($show['announcements'][0]['title'])->toBe('Bring a friend')->and($show['can_lead'])->toBeTrue();
});

// ── Finding people ─────────────────────────────────────────────────────────────────────────────────────

test('people are found only by the rules they chose, adults only, with filters', function () {
    $church = socialChurch();
    $other = socialChurch('Living Waters');
    $me = actingMember(socialMember($church, ['name' => 'Me Myself']));

    $same = socialMember($church, ['name' => 'Sam Samechurch', 'city' => 'Gulu', 'gender' => 'male', 'interests' => ['Worship', 'Missions']]);
    $hidden = socialMember($church, ['name' => 'Hidden Hannah', 'directory_opt_in' => false]);
    $minor = socialMember($church, ['name' => 'Minor Mike', 'date_of_birth' => now()->subYears(15)->toDateString()]);
    $noDob = socialMember($church, ['name' => 'Nodob Nina', 'date_of_birth' => null]);
    $faraway = socialMember($other, ['name' => 'Faraway Fred', 'gender' => 'male', 'directory_opt_in' => false, 'open_to_connect' => true, 'country' => 'KE', 'city' => 'Nairobi', 'date_of_birth' => now()->subYears(41)->toDateString()]);
    $privateFar = socialMember($other, ['name' => 'Private Pat', 'directory_opt_in' => true]);

    $names = fn ($params = []) => collect($this->getJson(route('api.v1.people.index', $params))->assertOk()->json('data'))->pluck('name')->sort()->values()->all();

    // My own church: members who listed themselves, adults with a birth date.
    expect($names())->toBe(['Sam Samechurch'])
        ->and($names(['scope' => 'everyone']))->toBe(['Faraway Fred', 'Sam Samechurch'])
        ->and($names(['scope' => 'everyone', 'country' => 'KE']))->toBe(['Faraway Fred'])
        ->and($names(['scope' => 'everyone', 'city' => 'Nairobi']))->toBe(['Faraway Fred'])
        ->and($names(['scope' => 'everyone', 'age_min' => 40]))->toBe(['Faraway Fred'])
        ->and($names(['scope' => 'everyone', 'age_max' => 35]))->toBe(['Sam Samechurch'])
        ->and($names(['interest' => 'Missions']))->toBe(['Sam Samechurch'])
        ->and($names(['gender' => 'female', 'scope' => 'everyone']))->toBe([])
        ->and($names(['q' => 'sam']))->toBe(['Sam Samechurch']);

    // The card shows an age bracket, never a birthday, and no contact details.
    $card = $this->getJson(route('api.v1.people.index', ['scope' => 'everyone', 'q' => 'Faraway']))->json('data.0');
    expect($card['age_range'])->toBe('35–44')->and($card['shared_churches'])->toBe([])->and($card['relation'])->toBe('none')
        ->and($card)->not->toHaveKeys(['email', 'phone', 'date_of_birth']);

    // Profiles obey the same rules.
    $this->getJson(route('api.v1.people.show', $same))->assertOk()->assertJsonPath('data.shared_churches.0.name', 'Grace Lira');
    $this->getJson(route('api.v1.people.show', $hidden))->assertNotFound();
    $this->getJson(route('api.v1.people.show', $minor))->assertNotFound();
    $this->getJson(route('api.v1.people.show', $noDob))->assertNotFound();
    $this->getJson(route('api.v1.people.show', $privateFar))->assertNotFound(); // listed only for their own church
    $this->getJson(route('api.v1.people.show', $faraway))->assertOk();
    unset($me);
});

test('members connect, answer requests, chat only once connected, and blocking cuts everything off', function () {
    $church = socialChurch();
    $ann = actingMember(socialMember($church, ['name' => 'Ann']));
    $ben = socialMember($church, ['name' => 'Ben']);
    $cat = socialMember($church, ['name' => 'Cat']);

    // Not connected: no chat.
    $this->postJson(route('api.v1.messages.send', $ben), ['body' => 'Hi'])->assertForbidden();

    $this->postJson(route('api.v1.people.connect', $ben), ['message' => 'Hello Ben, from the choir!'])->assertCreated()->assertJsonPath('status', 'pending')->assertJsonPath('data.relation', 'pending_sent');
    $this->postJson(route('api.v1.people.connect', $ben))->assertCreated(); // asking twice changes nothing
    expect(Connection::count())->toBe(1);

    actingMember($ben);
    $incoming = $this->getJson(route('api.v1.connections.index'))->json('data.incoming');
    expect($incoming)->toHaveCount(1)->and($incoming[0]['name'])->toBe('Ann')->and($incoming[0]['message'])->toBe('Hello Ben, from the choir!');
    expect($this->getJson(route('api.v1.notifications.index'))->json('data.0.kind'))->toBe('connection_request');
    expect($this->getJson(route('api.v1.me'))->json('data.connection_requests'))->toBe(1);

    // Ann cannot answer her own request; Ben accepts.
    $id = $incoming[0]['connection_id'];
    actingMember($ann);
    $this->postJson(route('api.v1.connections.respond', $id), ['action' => 'accept'])->assertNotFound();
    actingMember($ben);
    $this->postJson(route('api.v1.connections.respond', $id), ['action' => 'accept'])->assertOk()->assertJsonPath('status', 'accepted');

    // Now they can talk; unread counts and the conversation list follow.
    $this->postJson(route('api.v1.messages.send', $ann), ['body' => 'Welcome Ann <script>x</script>'])->assertCreated()->assertJsonPath('data.body', 'Welcome Ann x');
    actingMember($ann);
    expect($this->getJson(route('api.v1.messages.unread'))->json('unread'))->toBe(1);
    $list = $this->getJson(route('api.v1.messages.index'))->json('data');
    expect($list)->toHaveCount(1)->and($list[0]['user']['name'])->toBe('Ben')->and($list[0]['unread'])->toBe(1)->and($list[0]['last']['mine'])->toBeFalse();
    $this->postJson(route('api.v1.messages.send', $ben), ['body' => 'Thank you Ben!'])->assertCreated();
    $thread = $this->getJson(route('api.v1.messages.thread', $ben))->assertOk()->json();
    expect(collect($thread['data'])->pluck('body')->all())->toBe(['Welcome Ann x', 'Thank you Ben!'])->and($thread['partner']['can_message'])->toBeTrue();
    expect($this->getJson(route('api.v1.messages.unread'))->json('unread'))->toBe(0);
    $lastId = $thread['data'][1]['id'];
    expect($this->getJson(route('api.v1.messages.thread', [$ben, 'after' => $lastId]))->json('data'))->toBe([]);

    // A stranger who is not connected reads nothing.
    actingMember($cat);
    $this->getJson(route('api.v1.messages.thread', $ann))->assertNotFound();

    // Blocking removes the connection and hides everyone from each other.
    actingMember($ann);
    $this->postJson(route('api.v1.people.block', $ben))->assertOk();
    expect($this->getJson(route('api.v1.connections.index'))->json('data.friends'))->toBe([])
        ->and($this->getJson(route('api.v1.messages.index'))->json('data'))->toBe([])
        ->and(collect($this->getJson(route('api.v1.people.index'))->json('data'))->pluck('name')->all())->toBe(['Cat'])
        ->and($this->getJson(route('api.v1.people.blocked'))->json('data.0.name'))->toBe('Ben');
    $this->postJson(route('api.v1.messages.send', $ben), ['body' => 'hi'])->assertForbidden();
    actingMember($ben);
    $this->postJson(route('api.v1.messages.send', $ann), ['body' => 'hi'])->assertForbidden();
    $this->getJson(route('api.v1.people.show', $ann))->assertNotFound();
    $this->postJson(route('api.v1.people.connect', $ann))->assertUnprocessable();
    actingMember($ann);
    $this->deleteJson(route('api.v1.people.unblock', $ben))->assertOk();
    expect(collect($this->getJson(route('api.v1.people.index'))->json('data'))->pluck('name')->sort()->values()->all())->toBe(['Ben', 'Cat']);
});

test('asking someone who already asked you connects you straight away, and a declined request can be asked again', function () {
    $church = socialChurch();
    $ann = actingMember(socialMember($church));
    $ben = socialMember($church);

    $this->postJson(route('api.v1.people.connect', $ben))->assertCreated();
    actingMember($ben);
    $this->postJson(route('api.v1.people.connect', $ann))->assertCreated()->assertJsonPath('data.relation', 'connected');
    expect($this->getJson(route('api.v1.connections.index'))->json('data.friends'))->toHaveCount(1);

    $this->deleteJson(route('api.v1.people.disconnect', $ann))->assertOk();
    $this->postJson(route('api.v1.people.connect', $ann))->assertCreated();
    actingMember($ann);
    $id = $this->getJson(route('api.v1.connections.index'))->json('data.incoming.0.connection_id');
    $this->postJson(route('api.v1.connections.respond', $id), ['action' => 'decline'])->assertOk();
    expect($this->getJson(route('api.v1.connections.index'))->json('data.incoming'))->toBe([]);
    actingMember($ben);
    $this->postJson(route('api.v1.people.connect', $ann))->assertCreated()->assertJsonPath('data.relation', 'pending_sent');
});

test('a member without a birth date, or under 18, cannot connect, and nobody can reach a minor', function () {
    $church = socialChurch();
    $adult = socialMember($church);
    $young = actingMember(socialMember($church, ['date_of_birth' => now()->subYears(16)->toDateString()]));

    $this->postJson(route('api.v1.people.connect', $adult))->assertUnprocessable()->assertJsonValidationErrors('user');
    expect($this->getJson(route('api.v1.people.index'))->json('viewer.can_connect'))->toBeFalse();

    actingMember($adult);
    $this->postJson(route('api.v1.people.connect', $young))->assertUnprocessable();
});

test('there is a limit on requests waiting for an answer', function () {
    $church = socialChurch();
    actingMember(socialMember($church));
    foreach (range(1, 25) as $i) {
        $this->postJson(route('api.v1.people.connect', socialMember($church)))->assertCreated();
    }
    $this->postJson(route('api.v1.people.connect', socialMember($church)))->assertUnprocessable();
});

// ── My profile ────────────────────────────────────────────────────────────────────────────────────────

test('the profile editor controls who can find you and needs a birth date to be found', function () {
    $church = socialChurch();
    $me = actingMember(member(['date_of_birth' => null]));
    $church->subscriptions()->create(['user_id' => $me->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);

    $this->patchJson(route('api.v1.me.update'), ['open_to_connect' => true])->assertUnprocessable()->assertJsonValidationErrors('date_of_birth');
    $this->patchJson(route('api.v1.me.update'), ['date_of_birth' => now()->subYears(12)->toDateString(), 'directory_opt_in' => true])->assertUnprocessable()->assertJsonValidationErrors('date_of_birth');

    $this->patchJson(route('api.v1.me.update'), [
        'name' => 'Grace Akello', 'bio' => 'Choir and prayer.', 'city' => 'Lira', 'country' => 'ug', 'gender' => 'female',
        'date_of_birth' => now()->subYears(28)->toDateString(), 'interests' => ['Worship', ' Prayer ', 'worship', 'Prayer'], 'directory_opt_in' => true, 'open_to_connect' => true,
    ])->assertOk()->assertJsonPath('data.name', 'Grace Akello')->assertJsonPath('data.country', 'UG')->assertJsonPath('data.interests', ['Worship', 'Prayer', 'worship'])->assertJsonPath('data.open_to_connect', true);

    expect($me->fresh()->open_to_connect)->toBeTrue();
    $this->patchJson(route('api.v1.me.update'), ['gender' => 'robot'])->assertUnprocessable();
});

test('a member sets a photo and changes the password', function () {
    $user = actingMember(member(['password' => Hash::make('old-password-1')]));
    $user->createToken('other phone');

    $this->postJson(route('api.v1.me.avatar'), ['avatar' => UploadedFile::fake()->image('me.jpg', 300, 300)])->assertOk()->assertJsonPath('data.avatar_url', fn ($url) => str_contains((string) $url, '/storage/'));
    $this->deleteJson(route('api.v1.me.avatar.remove'))->assertOk()->assertJsonPath('data.avatar_url', null);
    $this->postJson(route('api.v1.me.avatar'), ['avatar' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertUnprocessable();

    $this->putJson(route('api.v1.me.password'), ['current_password' => 'wrong', 'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9'])->assertUnprocessable();
    $this->putJson(route('api.v1.me.password'), ['current_password' => 'old-password-1', 'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9'])->assertOk();
    expect(Hash::check('brand-new-pass-9', $user->fresh()->password))->toBeTrue();
});
