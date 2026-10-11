<?php

use App\Models\Church;
use App\Models\NotificationPreference;
use App\Models\Post;
use App\Models\Subscription;
use App\Notifications\Church\NewSubscriber;
use App\Notifications\Member\WelcomeMember;
use App\Services\Notifications\PreferenceResolver;
use Illuminate\Support\Facades\Notification;

function activeChurchWithAdmin(array $attributes = []): array
{
    $admin = member();
    $church = Church::factory()->create($attributes);
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);

    return [$church, $admin];
}

test('a member can subscribe to many churches and unsubscribe', function () {
    Notification::fake();
    [$a, $adminA] = activeChurchWithAdmin();
    [$b] = activeChurchWithAdmin();
    $user = member();

    $this->actingAs($user)->post(route('churches.subscribe', $a))->assertRedirect();
    $this->actingAs($user)->post(route('churches.subscribe', $b))->assertRedirect();

    expect($user->fresh()->subscribedChurchIds())->toEqualCanonicalizing([$a->id, $b->id]);
    Notification::assertSentTo($user, WelcomeMember::class);
    Notification::assertSentTo($adminA, NewSubscriber::class);

    $this->actingAs($user)->delete(route('churches.unsubscribe', $a))->assertRedirect();
    expect($user->fresh()->subscribedChurchIds())->toBe([$b->id])
        ->and(Subscription::where('user_id', $user->id)->where('church_id', $a->id)->value('status'))->toBe('left');
});

test('re-subscribing keeps the original subscription history', function () {
    [$church] = activeChurchWithAdmin();
    $user = member();

    $this->actingAs($user)->post(route('churches.subscribe', $church));
    $first = Subscription::first()->subscribed_at;
    $this->actingAs($user)->delete(route('churches.unsubscribe', $church));
    $this->travel(2)->days();
    $this->actingAs($user)->post(route('churches.subscribe', $church));

    expect(Subscription::count())->toBe(1)
        ->and(Subscription::first()->status)->toBe('active')
        ->and(Subscription::first()->subscribed_at->equalTo($first))->toBeTrue();
});

test('churches requiring approval put subscribers in pending until approved', function () {
    Notification::fake();
    [$church, $admin] = activeChurchWithAdmin(['requires_member_approval' => true]);
    $user = member();

    $this->actingAs($user)->post(route('churches.subscribe', $church));
    $subscription = Subscription::first();
    expect($subscription->status)->toBe('pending')->and($user->fresh()->isSubscribedTo($church))->toBeFalse();

    $this->actingAs($admin)->put(route('church-admin.members.update', [$church, $subscription]), ['action' => 'approve'])->assertRedirect();
    expect($subscription->fresh()->status)->toBe('active');
    Notification::assertSentTo($user, WelcomeMember::class);
});

test('blocked members cannot re-subscribe', function () {
    [$church, $admin] = activeChurchWithAdmin();
    $user = member();
    $this->actingAs($user)->post(route('churches.subscribe', $church));

    $this->actingAs($admin)->put(route('church-admin.members.update', [$church, Subscription::first()]), ['action' => 'block']);
    $this->actingAs($user)->delete(route('churches.unsubscribe', $church));
    $this->actingAs($user)->post(route('churches.subscribe', $church))->assertForbidden();
});

test('cannot subscribe to a church that is not active', function () {
    $church = Church::factory()->pending()->create();

    $this->actingAs(member())->post(route('churches.subscribe', $church))->assertStatus(422);
});

test('feed shows posts from followed churches only, respecting visibility', function () {
    [$mine] = activeChurchWithAdmin(['name' => 'My Church']);
    [$other] = activeChurchWithAdmin(['name' => 'Other Church']);
    $user = member();
    $mine->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);

    Post::create(['church_id' => $mine->id, 'body' => 'Members only note', 'visibility' => 'members', 'status' => 'published', 'published_at' => now()->subHour()]);
    Post::create(['church_id' => $other->id, 'body' => 'Not followed news', 'visibility' => 'public', 'status' => 'published', 'published_at' => now()->subHour()]);
    Post::create(['church_id' => $mine->id, 'body' => 'Scheduled for later', 'visibility' => 'public', 'status' => 'published', 'published_at' => now()->addDay()]);
    Post::create(['church_id' => $mine->id, 'body' => 'Hidden by moderator', 'visibility' => 'public', 'status' => 'hidden', 'published_at' => now()->subHour()]);

    $this->actingAs($user)->get('/dashboard')
        ->assertSee('Members only note')
        ->assertDontSee('Not followed news')
        ->assertDontSee('Scheduled for later')
        ->assertDontSee('Hidden by moderator');
});

test('members-only posts are hidden on the public church page for non-members', function () {
    [$church] = activeChurchWithAdmin();
    Post::create(['church_id' => $church->id, 'body' => 'Public hello', 'visibility' => 'public', 'status' => 'published', 'published_at' => now()->subHour()]);
    Post::create(['church_id' => $church->id, 'body' => 'Private family news', 'visibility' => 'members', 'status' => 'published', 'published_at' => now()->subHour()]);

    $this->get(route('churches.show', $church))->assertSee('Public hello')->assertDontSee('Private family news');
});

test('notification preferences resolve church, global and default levels', function () {
    [$church] = activeChurchWithAdmin();
    $user = member();
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);
    $resolver = new PreferenceResolver;

    expect($resolver->channelsFor($user, $church->id, 'live'))->toBe(NotificationPreference::DEFAULT_CHANNELS['live']);

    $this->actingAs($user)->put(route('notification-preferences.update'), [
        'scope' => 'global', 'prefs' => ['global' => ['live' => ['sms']]],
    ])->assertRedirect();
    expect((new PreferenceResolver)->channelsFor($user, $church->id, 'live'))->toBe(['sms']);

    $this->actingAs($user)->put(route('notification-preferences.update'), [
        'scope' => (string) $church->id, 'prefs' => [$church->id => ['live' => ['mail', 'database']]],
    ]);
    $fresh = new PreferenceResolver;
    expect($fresh->channelsFor($user, $church->id, 'live'))->toBe(['mail', 'database'])
        ->and($fresh->channelsFor($user, $church->id, 'sermon'))->toBe([]);
});

test('users cannot set preferences for churches they do not follow', function () {
    [$church] = activeChurchWithAdmin();
    $user = member();

    $this->actingAs($user)->put(route('notification-preferences.update'), [
        'scope' => (string) $church->id, 'prefs' => [$church->id => ['live' => ['sms']]],
    ]);

    expect(NotificationPreference::where('church_id', $church->id)->exists())->toBeFalse();
});

test('muting a church disables all its categories', function () {
    [$church] = activeChurchWithAdmin();
    $user = member();
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);

    $this->actingAs($user)->put(route('churches.mute', $church), ['muted' => 1])->assertRedirect();

    expect((new PreferenceResolver)->channelsFor($user, $church->id, 'live'))->toBe([]);
});

test('member directory only lists opted-in members to fellow members', function () {
    [$church] = activeChurchWithAdmin();
    $viewer = member();
    $listed = member(['name' => 'Listed Person', 'directory_opt_in' => true]);
    $hidden = member(['name' => 'Hidden Person', 'directory_opt_in' => false]);
    foreach ([$viewer, $listed, $hidden] as $u) {
        $church->subscriptions()->create(['user_id' => $u->id, 'status' => 'active', 'subscribed_at' => now()]);
    }

    $this->actingAs($viewer)->get(route('directory.index', ['church' => $church->slug]))
        ->assertSee('Listed Person')->assertDontSee('Hidden Person');

    $this->actingAs(member())->get(route('directory.index', ['church' => $church->slug]))->assertForbidden();
});

test('notification center marks notifications as read', function () {
    [$church] = activeChurchWithAdmin();
    $user = member();
    $user->notify(new WelcomeMember($church));
    $notification = $user->notifications()->first();

    expect($notification->church_id)->toBe($church->id)->and($notification->category)->toBe('announcement');
    $this->actingAs($user)->getJson(route('notifications.count'))->assertJson(['unread' => 1]);
    $this->actingAs($user)->get(route('notifications.open', $notification->id))->assertRedirect(route('churches.show', $church));
    $this->actingAs($user)->getJson(route('notifications.count'))->assertJson(['unread' => 0]);
});

test('member export only includes emails of members who consented', function () {
    [$church, $admin] = activeChurchWithAdmin();
    $consenting = member(['email' => 'yes@consent.test']);
    $private = member(['email' => 'no@consent.test']);
    $church->subscriptions()->create(['user_id' => $consenting->id, 'status' => 'active', 'share_email' => true, 'subscribed_at' => now()]);
    $church->subscriptions()->create(['user_id' => $private->id, 'status' => 'active', 'share_email' => false, 'subscribed_at' => now()]);

    $csv = $this->actingAs($admin)->get(route('church-admin.members.export', $church))->streamedContent();

    expect($csv)->toContain('yes@consent.test')->not->toContain('no@consent.test');
});

test('church admins can publish feed posts', function () {
    [$church, $admin] = activeChurchWithAdmin();

    $this->actingAs($admin)->post(route('church-admin.posts.store', $church), [
        'body' => 'Choir practice moved to Saturday', 'visibility' => 'public',
    ])->assertRedirect();

    expect($church->posts()->where('body', 'Choir practice moved to Saturday')->exists())->toBeTrue();
});
