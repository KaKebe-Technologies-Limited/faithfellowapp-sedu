<?php

use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Subscription;
use App\Notifications\Member\WelcomeMember;
use Laravel\Sanctum\Sanctum;

/*
| The platform admin puts registered users into a church: they follow it at once, without finding it themselves, whatever the church's approval setting
| and the limit of churches a member follows. It is done from the church's page (a list of emails or phone numbers, or people found by name) or from the
| user's page.
*/

function followState(Church $church, $user): ?string
{
    return Subscription::where('church_id', $church->id)->where('user_id', $user->id)->value('status');
}

test('the super admin adds registered users by email and phone number, and is told who is not registered yet', function () {
    Notification::fake();
    $church = Church::factory()->create(['name' => 'Grace Church']);
    $ann = member(['email' => 'ann@example.test']);
    $ben = member(['phone' => '0772 123 456']);

    $this->actingAs(superAdmin())->post(route('super-admin.churches.members.assign', $church), [
        'identifiers' => "ANN@example.test\n+256 772123456, nobody@example.test; 12345",
        'stage' => 'worker', 'notify' => 1,
    ])->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, '2 people now follow Grace Church') && str_contains($m, 'Not registered yet: nobody@example.test, 12345'));

    expect(followState($church, $ann))->toBe('active')->and(followState($church, $ben))->toBe('active');
    $row = Subscription::where('church_id', $church->id)->where('user_id', $ann->id)->first();
    expect($row->source)->toBe('assigned')->and($row->stage)->toBe('worker')->and($row->approved_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'member.assigned')->count())->toBe(2);
    Notification::assertSentTo([$ann, $ben], WelcomeMember::class);

    // They see the church the moment they open the app.
    Sanctum::actingAs($ann, ['member']);
    expect(collect($this->getJson(route('api.v1.home'))->assertOk()->json('data.churches'))->pluck('name')->all())->toBe(['Grace Church']);
});

test('it works whatever the member limit or the church\'s approval setting say, and not twice', function () {
    Notification::fake();
    $church = Church::factory()->create(['requires_member_approval' => true]);
    $busy = member();
    foreach (Church::factory()->count(3)->create() as $other) {
        $other->subscriptions()->create(['user_id' => $busy->id, 'status' => 'active', 'subscribed_at' => now()]);
    }
    $asked = member();
    $church->subscriptions()->create(['user_id' => $asked->id, 'status' => 'pending', 'subscribed_at' => now()]);
    $super = superAdmin();

    $this->actingAs($super)->post(route('super-admin.churches.members.assign', $church), ['identifiers' => $busy->email.' '.$asked->email])->assertSessionHas('success', fn ($m) => str_contains($m, '2 people now follow'));
    expect(followState($church, $busy))->toBe('active')->and(followState($church, $asked))->toBe('active')
        ->and($busy->fresh()->subscriptions()->where('status', 'active')->count())->toBe(4);

    $this->actingAs($super)->post(route('super-admin.churches.members.assign', $church), ['identifiers' => $busy->email])->assertSessionHas('success', fn ($m) => str_contains($m, '1 already followed it'));
    expect(Subscription::where('church_id', $church->id)->count())->toBe(2);
});

test('someone the church blocked stays blocked, a suspended account is skipped, and a member who left is brought back', function () {
    $church = Church::factory()->create();
    $blocked = member();
    $church->subscriptions()->create(['user_id' => $blocked->id, 'status' => 'blocked', 'subscribed_at' => now()]);
    $suspended = member(['status' => 'suspended']);
    $left = member();
    $church->subscriptions()->create(['user_id' => $left->id, 'status' => 'left', 'subscribed_at' => now()->subYear(), 'unsubscribed_at' => now()->subMonth()]);

    $this->actingAs(superAdmin())->post(route('super-admin.churches.members.assign', $church), ['identifiers' => implode(' ', [$blocked->email, $suspended->email, $left->email])])
        ->assertSessionHas('success', fn ($m) => str_contains($m, '1 person now follow') && str_contains($m, 'Blocked by the church') && str_contains($m, 'Suspended accounts were skipped'));

    expect(followState($church, $blocked))->toBe('blocked')->and(followState($church, $suspended))->toBeNull()->and(followState($church, $left))->toBe('active')
        ->and(Subscription::where('church_id', $church->id)->where('user_id', $left->id)->value('unsubscribed_at'))->toBeNull();
});

test('people can be found by name on the church page and added with a tick', function () {
    Notification::fake();
    $church = Church::factory()->create();
    $found = member(['name' => 'Grace Akello', 'email' => 'grace.a@example.test']);
    $already = member(['name' => 'Grace Opio']);
    $church->subscriptions()->create(['user_id' => $already->id, 'status' => 'active', 'subscribed_at' => now()]);
    member(['name' => 'Peter Okello']);
    $super = superAdmin();

    $this->actingAs($super)->get(route('super-admin.churches.show', [$church, 'find' => 'grace']))->assertOk()
        ->assertSee('Grace Akello')->assertSee('Grace Opio')->assertSee('Already follows')->assertDontSee('Peter Okello')->assertSee('Add the ticked people');
    $this->actingAs($super)->get(route('super-admin.churches.show', [$church, 'find' => 'zzzz']))->assertOk()->assertSee('Nobody registered matches');

    $this->actingAs($super)->post(route('super-admin.churches.members.assign', $church), ['user_ids' => [$found->id], 'stage' => 'member', 'notify' => 1])->assertSessionHas('success');
    expect(followState($church, $found))->toBe('active');
    $this->actingAs($super)->get(route('super-admin.churches.show', $church))->assertOk()->assertSee('Followers (2)')->assertSee('Added by FaithFellows');
});

test('on the Users page the super admin presses Assign and puts the person into a church, or two', function () {
    Notification::fake();
    $user = member(['name' => 'Naomi Newcomer']);
    $first = Church::factory()->create(['name' => 'Hope Chapel']);
    $second = Church::factory()->create(['name' => 'Grace Church']);
    $third = Church::factory()->create(['name' => 'Third Church']);
    $super = superAdmin();

    // The list has an Assign button on each row, and a window with every active church to tick.
    $this->actingAs($super)->get(route('super-admin.users.index'))->assertOk()->assertSee('Assign to a church')->assertSee('id="assignModal"', false)
        ->assertSee(route('super-admin.users.churches.assign', $user), false)->assertSee('Hope Chapel')->assertSee('Third Church');

    $this->actingAs($super)->post(route('super-admin.users.churches.assign', $user), ['church_ids' => [$first->id, $second->id], 'notify' => 1])
        ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'Naomi Newcomer now follows Grace Church and Hope Chapel.'));
    expect(followState($first, $user))->toBe('active')->and(followState($second, $user))->toBe('active')->and(followState($third, $user))->toBeNull();
    Notification::assertSentToTimes($user, WelcomeMember::class, 2);

    // The list now names the churches the person follows, and the window will open with them already ticked.
    $row = $this->actingAs($super)->get(route('super-admin.users.index', ['q' => 'Naomi']))->assertOk()->assertSee('Hope Chapel');
    expect($row->getContent())->toContain('data-followed="['.$second->id.','.$first->id.']"');

    // Ticking one they already follow and one new says so plainly; nothing ticked asks for a tick.
    $this->actingAs($super)->post(route('super-admin.users.churches.assign', $user), ['church_ids' => [$first->id, $third->id]])
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'now follows Third Church') && str_contains($m, 'Already followed: Hope Chapel'));
    $this->actingAs($super)->post(route('super-admin.users.churches.assign', $user), [])->assertSessionHasErrors('church_ids');

    // A user's own page has the same ticks, and Remove takes a church away again.
    $this->actingAs($super)->delete(route('super-admin.churches.members.remove', [$first, $user]))->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'no longer follows Hope Chapel'));
    expect(followState($first, $user))->toBe('left');
    $this->actingAs($super)->get(route('super-admin.users.show', $user))->assertOk()->assertSee('Assign to a church')->assertSee('Hope Chapel');
    Sanctum::actingAs($user, ['member']);
    expect(collect($this->getJson(route('api.v1.home'))->json('data.churches'))->pluck('name')->sort()->values()->all())->toBe(['Grace Church', 'Third Church']);
});

test('a suspended account has no Assign button and cannot be added to a church', function () {
    $user = member(['name' => 'Sam Suspended', 'status' => 'suspended']);
    $church = Church::factory()->create();
    $super = superAdmin();

    $this->actingAs($super)->get(route('super-admin.users.index', ['q' => 'Sam Suspended']))->assertOk()->assertDontSee(route('super-admin.users.churches.assign', $user), false);
    $this->actingAs($super)->post(route('super-admin.users.churches.assign', $user), ['church_ids' => [$church->id]])->assertSessionHas('success', fn ($m) => str_contains($m, 'suspended'));
    expect(followState($church, $user))->toBeNull();
});

test('it can be switched off to add people quietly, and only the super admin can do it', function () {
    Notification::fake();
    $church = Church::factory()->create();
    $quiet = member();

    $this->actingAs(superAdmin())->post(route('super-admin.churches.members.assign', $church), ['identifiers' => $quiet->email])->assertSessionHas('success'); // "Tell them" not ticked
    Notification::assertNothingSent();
    expect(followState($church, $quiet))->toBe('active');

    // Not the church's admin, not a member, nobody signed out.
    $admin = member();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);
    $target = member();
    foreach ([$admin, member()] as $who) {
        $this->actingAs($who)->post(route('super-admin.churches.members.assign', $church), ['identifiers' => $target->email])->assertForbidden();
        $this->actingAs($who)->post(route('super-admin.users.churches.assign', $target), ['church_ids' => [$church->id]])->assertForbidden();
        $this->actingAs($who)->get(route('super-admin.users.index'))->assertForbidden();
        $this->actingAs($who)->delete(route('super-admin.churches.members.remove', [$church, $quiet]))->assertForbidden();
    }
    expect(followState($church, $target))->toBeNull();

    // An empty form says what is missing, and a suspended church takes nobody.
    $this->actingAs(superAdmin())->post(route('super-admin.churches.members.assign', $church), [])->assertSessionHasErrors('identifiers');
    $suspended = Church::factory()->create(['status' => 'suspended']);
    $this->actingAs(superAdmin())->post(route('super-admin.churches.members.assign', $suspended), ['identifiers' => $target->email])->assertSessionHas('success', fn ($m) => str_contains($m, 'not active'));
    expect(followState($suspended, $target))->toBeNull();
});
