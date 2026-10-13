<?php

use App\Models\Church;
use App\Models\Comment;
use App\Models\ContentReport;
use App\Models\EventRsvp;
use App\Models\Quiz;
use App\Models\User;
use App\Notifications\Church\ChurchMessage;
use App\Notifications\Content\AnnouncementPublished;
use App\Notifications\Live\LiveStreamStarted;
use App\Notifications\Prayer\PrayerRequestShared;
use App\Services\ModerationService;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/*
| Running a church from the app: only staff get in, each person sees and does only what their role allows, and
| every action reuses the website's own services (notifications, approvals, check-in, quizzes, streaming).
*/

/** @return array{0: Church, 1: User, 2: User} church, its admin and an ordinary member */
function adminChurch(): array
{
    $church = Church::factory()->create(['name' => 'Grace Lira', 'slug' => 'grace-lira']);
    $admin = member();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);
    $follower = member();
    $church->subscriptions()->create(['user_id' => $follower->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);

    return [$church, $admin, $follower];
}

function staffWith(Church $church, array $permissions): User
{
    $role = $church->roles()->create(['name' => 'Helper '.implode(',', $permissions), 'slug' => 'helper-'.md5(implode(',', $permissions)), 'permissions' => $permissions]);
    $user = member();
    $church->staffMembers()->create(['user_id' => $user->id, 'church_role_id' => $role->id, 'status' => 'active']);

    return $user;
}

function actAs(User $user): void
{
    Sanctum::actingAs($user, ['member']);
}

test('the app lists the churches a member helps run, and the dashboard shows only what their role allows', function () {
    [$church, $admin, $follower] = adminChurch();
    $prayerHelper = staffWith($church, ['prayer.moderate']);
    $church->prayerRequests()->create(['user_id' => $follower->id, 'body' => 'Please pray for rain', 'visibility' => 'church', 'status' => 'pending']);

    actAs($follower);
    expect($this->getJson(route('api.v1.admin.churches'))->assertOk()->json('data'))->toBe([]);
    expect($this->getJson(route('api.v1.me'))->json('data.staff_of'))->toBe([]);
    $this->getJson(route('api.v1.admin.dashboard', $church->slug))->assertForbidden();

    actAs($admin);
    expect($this->getJson(route('api.v1.me'))->json('data.staff_of.0.slug'))->toBe('grace-lira');
    $list = $this->getJson(route('api.v1.admin.churches'))->json('data');
    expect($list[0]['church']['name'])->toBe('Grace Lira')->and($list[0]['is_admin'])->toBeTrue()->and($list[0]['permissions'])->toContain('content.manage', 'giving.view');
    $stats = $this->getJson(route('api.v1.admin.dashboard', $church->slug))->assertOk()->json('data.stats');
    expect($stats['members'])->toBe(1)->and($stats['pending_prayers'])->toBe(1)->and($stats['giving_month'])->toEqual(0);

    // A prayer helper sees prayer numbers, but not money, members or reports.
    actAs($prayerHelper);
    $stats = $this->getJson(route('api.v1.admin.dashboard', $church->slug))->assertOk()->json('data.stats');
    expect($stats['pending_prayers'])->toBe(1)->and($stats['giving_month'])->toBeNull()->and($stats['pending_members'])->toBeNull()->and($stats['open_reports'])->toBeNull();
    $this->getJson(route('api.v1.admin.giving.summary', $church->slug))->assertForbidden();
    $this->postJson(route('api.v1.admin.announcements.store', $church->slug), ['title' => 'x', 'body' => 'y', 'audience' => 'members', 'priority' => 'normal', 'status' => 'published'])->assertForbidden();

    // Staff of another church cannot use this church's admin.
    $other = Church::factory()->create();
    $stranger = member();
    $other->staffMembers()->create(['user_id' => $stranger->id, 'is_admin' => true, 'status' => 'active']);
    actAs($stranger);
    $this->getJson(route('api.v1.admin.dashboard', $church->slug))->assertForbidden();
});

test('staff publish announcements, devotionals and sermons that members then see', function () {
    Notification::fake();
    [$church, $admin, $follower] = adminChurch();
    actAs($admin);

    $notice = $this->postJson(route('api.v1.admin.announcements.store', $church->slug), ['title' => 'Road closed', 'body' => "Use the back gate.\nThank you.", 'audience' => 'members', 'priority' => 'urgent', 'status' => 'published'])->assertCreated()->json('data');
    $this->postJson(route('api.v1.admin.announcements.store', $church->slug), ['title' => 'Draft idea', 'body' => 'later', 'audience' => 'members', 'priority' => 'normal', 'status' => 'draft'])->assertCreated();
    $this->postJson(route('api.v1.admin.announcements.store', $church->slug), ['title' => '', 'body' => 'x'])->assertUnprocessable();
    Notification::assertSentTo($follower, AnnouncementPublished::class);

    $this->postJson(route('api.v1.admin.devotionals.store', $church->slug), ['title' => 'Be still', 'scripture' => 'Psalm 46:10', 'body' => 'Rest in Him.', 'publish_date' => today()->toDateString(), 'status' => 'published', 'visibility' => 'public'])->assertCreated();
    $this->postJson(route('api.v1.admin.sermons.store', $church->slug), ['title' => 'Grace alone', 'preacher' => 'Rev. Okello', 'language' => 'en', 'status' => 'published', 'visibility' => 'public', 'audio_url' => 'https://cdn.example.test/grace.mp3'])->assertCreated();

    actAs($follower);
    expect($this->getJson(route('api.v1.announcements.index'))->json('data.*.title'))->toBe(['Road closed'])
        ->and($this->getJson(route('api.v1.announcements.show', $notice['id']))->json('data.body'))->toBe("Use the back gate.\n\nThank you.")
        ->and($this->getJson(route('api.v1.devotionals.index'))->json('data.0.title'))->toBe('Be still')
        ->and($this->getJson(route('api.v1.sermons.index'))->json('data.0.audio_url'))->toBe('https://cdn.example.test/grace.mp3');

    actAs($admin);
    expect($this->getJson(route('api.v1.admin.announcements.index', $church->slug))->json('data'))->toHaveCount(2);
    $this->deleteJson(route('api.v1.admin.announcements.destroy', [$church->slug, $notice['id']]))->assertOk();
    expect($this->getJson(route('api.v1.admin.announcements.index', $church->slug))->json('data'))->toHaveCount(1);
});

test('staff create an event with tickets and check people in at the door by code or QR address', function () {
    Notification::fake();
    [$church, $admin, $follower] = adminChurch();
    actAs($admin);

    $event = $this->postJson(route('api.v1.admin.events.store', $church->slug), [
        'title' => 'Youth Night', 'description' => 'Worship and fun', 'starts_at' => now()->addDays(6)->toDateTimeString(), 'status' => 'published', 'visibility' => 'public',
        'rsvp_enabled' => true, 'capacity' => 50, 'is_paid' => true, 'tickets' => [['name' => 'Standard', 'price' => 5000, 'quantity' => 40]],
        'agenda' => [['time' => '18:00', 'title' => 'Worship']], 'speakers' => [['name' => 'Pastor Ruth', 'role' => 'Youth pastor']],
    ])->assertCreated()->json('data');
    expect($event['cover_url'])->toContain('/art/event/');

    // A member books it (free-of-charge path: choose the ticket, then it is pending payment; mark going directly for the door test).
    actAs($follower);
    $detail = $this->getJson(route('api.v1.events.show', $event['id']))->assertOk()->json('data');
    expect($detail['tickets'][0]['name'])->toBe('Standard')->and($detail['speakers'][0]['role'])->toBe('Youth pastor');
    $this->postJson(route('api.v1.events.rsvp', $event['id']), ['event_ticket_id' => $detail['tickets'][0]['id']])->assertCreated();
    $rsvp = EventRsvp::sole();
    $rsvp->update(['status' => 'going']);

    actAs($admin);
    $show = $this->getJson(route('api.v1.admin.events.show', [$church->slug, $event['id']]))->assertOk()->json('data');
    expect($show['stats']['going'])->toBe(1)->and($show['attendees'][0]['code'])->toBe($rsvp->code)->and($show['attendees'][0]['checked_in'])->toBeFalse();

    // Typing the code, then the same ticket again.
    $this->postJson(route('api.v1.admin.events.check-in', [$church->slug, $event['id']]), ['code' => strtolower($rsvp->code)])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('checked_in', 1);
    $again = $this->postJson(route('api.v1.admin.events.check-in', [$church->slug, $event['id']]), ['code' => $rsvp->code])->assertStatus(422)->json();
    expect($again['ok'])->toBeFalse()->and($again['message'])->toContain('Already checked in');
    $this->postJson(route('api.v1.admin.events.check-in', [$church->slug, $event['id']]), ['code' => 'NOSUCHCODE'])->assertStatus(422);

    // A scanned QR code is the whole check-in address; the code is read from its end.
    EventRsvp::whereKey($rsvp->id)->update(['checked_in_at' => null]);
    $this->postJson(route('api.v1.admin.events.check-in', [$church->slug, $event['id']]), ['code' => $rsvp->checkInUrl()])->assertOk()->assertJsonPath('attendee.name', $follower->name);

    $this->putJson(route('api.v1.admin.events.update', [$church->slug, $event['id']]), ['title' => 'Youth Night 2', 'starts_at' => now()->addDays(7)->toDateTimeString(), 'status' => 'published', 'visibility' => 'public', 'tickets' => [['id' => $detail['tickets'][0]['id'], 'name' => 'Standard', 'price' => 6000]]])->assertOk()->assertJsonPath('data.title', 'Youth Night 2');
    $this->deleteJson(route('api.v1.admin.events.destroy', [$church->slug, $event['id']]))->assertOk();
});

test('the prayer queue: approve to share with the church, decline to keep private, reply privately', function () {
    Notification::fake();
    [$church, $admin, $follower] = adminChurch();
    $church->subscriptions()->create(['user_id' => member()->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);

    actAs($follower);
    $first = $this->postJson(route('api.v1.prayer.store'), ['church_id' => $church->id, 'body' => 'Please pray for my exams', 'visibility' => 'church'])->assertCreated()->json('data');
    $second = $this->postJson(route('api.v1.prayer.store'), ['church_id' => $church->id, 'body' => 'A private struggle', 'visibility' => 'church'])->assertCreated()->json('data');
    $private = $this->postJson(route('api.v1.prayer.store'), ['church_id' => $church->id, 'body' => 'For my pastors only', 'visibility' => 'pastor'])->assertCreated()->json('data');

    actAs($admin);
    expect($this->getJson(route('api.v1.admin.prayer.index', $church->slug))->json('counts.pending'))->toBe(2);
    $this->postJson(route('api.v1.admin.prayer.approve', [$church->slug, $first['id']]))->assertOk()->assertJsonPath('status', 'open');
    $this->postJson(route('api.v1.admin.prayer.decline', [$church->slug, $second['id']]), ['note' => 'Praying with you'])->assertOk();
    $this->postJson(route('api.v1.admin.prayer.respond', [$church->slug, $private['id']]), ['response' => 'We are praying for you.'])->assertOk();
    Notification::assertSentTo(User::where('id', '!=', $follower->id)->where('id', '!=', $admin->id)->first(), PrayerRequestShared::class);

    actAs($follower);
    // The wall shows the approved request, and the author also sees their own private one.
    expect($this->getJson(route('api.v1.prayer.index'))->json('data.*.body'))->toBe(['For my pastors only', 'Please pray for my exams']);
    expect($this->getJson(route('api.v1.prayer.show', $private['id']))->json('data.pastor_response'))->toBe('We are praying for you.');

    actAs($admin);
    $this->postJson(route('api.v1.admin.prayer.hide', [$church->slug, $first['id']]))->assertOk()->assertJsonPath('status', 'hidden');
    actAs($follower);
    expect($this->getJson(route('api.v1.prayer.index'))->json('data.*.body'))->toBe(['For my pastors only']);
});

test('staff approve new members, change their stage and message the whole church', function () {
    Notification::fake();
    [$church, $admin, $follower] = adminChurch();
    $newcomer = member(['phone' => '0772111222']);
    $pending = $church->subscriptions()->create(['user_id' => $newcomer->id, 'status' => 'pending', 'stage' => 'visitor', 'subscribed_at' => now()]);

    actAs($admin);
    $list = $this->getJson(route('api.v1.admin.members.index', $church->slug))->assertOk()->json();
    expect($list['counts']['pending'])->toBe(1)->and(collect($list['data'])->pluck('name')->all())->toContain($newcomer->name);
    expect($this->getJson(route('api.v1.admin.members.index', [$church->slug, 'status' => 'pending']))->json('data'))->toHaveCount(1);

    $this->putJson(route('api.v1.admin.members.update', [$church->slug, $pending->id]), ['action' => 'approve'])->assertOk()->assertJsonPath('status', 'active');
    $this->putJson(route('api.v1.admin.members.update', [$church->slug, $pending->id]), ['stage' => 'new_member'])->assertOk()->assertJsonPath('stage', 'new_member');
    $this->putJson(route('api.v1.admin.members.update', [$church->slug, $pending->id]), ['stage' => 'nonsense'])->assertUnprocessable();

    $sent = $this->postJson(route('api.v1.admin.broadcasts.store', $church->slug), ['title' => 'Sunday service moved', 'body' => 'We start at 9 AM this week.', 'audience' => 'members', 'category' => 'announcement', 'send' => 'now'])->assertCreated()->json('data');
    expect($sent['status'])->toBe('sent')->and($sent['recipients'])->toBe(3); // the member, the new member and the admin
    Notification::assertSentTo($follower, ChurchMessage::class);
    $this->postJson(route('api.v1.admin.broadcasts.store', $church->slug), ['title' => 'Later', 'body' => 'x', 'audience' => 'members', 'category' => 'announcement', 'send' => 'later', 'scheduled_at' => now()->subDay()->toDateTimeString()])->assertUnprocessable();
    expect($this->getJson(route('api.v1.admin.broadcasts.index', $church->slug))->json('data.0.title'))->toBe('Sunday service moved');
});

test('staff write a quiz, host it, drive it from the phone, and members play it', function () {
    [$church, $admin, $follower] = adminChurch();
    actAs($admin);

    $this->postJson(route('api.v1.admin.quizzes.store', $church->slug), ['title' => 'Empty', 'questions' => []])->assertUnprocessable();
    $quiz = $this->postJson(route('api.v1.admin.quizzes.store', $church->slug), [
        'title' => 'Bible Basics',
        'questions' => [
            ['question' => 'Who built the ark?', 'options' => ['Moses', 'Noah', '', 'David'], 'correct' => 1],
            ['question' => 'How many disciples?', 'options' => ['Ten', 'Twelve'], 'correct' => 1, 'points' => 2000],
        ],
    ])->assertCreated()->json('data');
    expect($quiz['questions'])->toBe(2);
    expect(Quiz::sole()->questions[0]->options)->toBe(['Moses', 'Noah', 'David'])->and(Quiz::sole()->questions[0]->correct_option)->toBe(1);

    $session = $this->postJson(route('api.v1.admin.quizzes.host', [$church->slug, $quiz['id']]))->assertCreated()->json('data');
    expect($session['pin'])->toHaveLength(6)->and($session['status'])->toBe('lobby');
    expect($this->getJson(route('api.v1.admin.quizzes.index', $church->slug))->json('data.active.0.pin'))->toBe($session['pin']);

    actAs($follower);
    $this->postJson(route('api.v1.games.join'), ['pin' => $session['pin']])->assertOk();

    actAs($admin);
    $started = $this->postJson(route('api.v1.admin.quiz-sessions.action', [$church->slug, $session['id'], 'start']))->assertOk()->json('data');
    expect($started['status'])->toBe('question')->and($started['players_count'])->toBe(1)->and($started)->toHaveKey('reveal'); // the host sees the answer counts

    actAs($follower);
    $this->postJson(route('api.v1.games.answer', $session['id']), ['option' => 1, 'question' => 0])->assertOk();

    actAs($admin);
    $this->postJson(route('api.v1.admin.quiz-sessions.action', [$church->slug, $session['id'], 'reveal']))->assertOk()->assertJsonPath('data.reveal.counts.1', 1);
    $this->postJson(route('api.v1.admin.quiz-sessions.action', [$church->slug, $session['id'], 'next']))->assertOk()->assertJsonPath('data.index', 1);
    $this->postJson(route('api.v1.admin.quiz-sessions.action', [$church->slug, $session['id'], 'end']))->assertOk()->assertJsonPath('data.status', 'finished');
    $this->postJson(route('api.v1.admin.quiz-sessions.action', [$church->slug, $session['id'], 'explode']))->assertNotFound();

    // Without the quizzes permission the whole area is closed.
    actAs(staffWith($church, ['prayer.moderate']));
    $this->getJson(route('api.v1.admin.quizzes.index', $church->slug))->assertForbidden();
});

test('staff schedule a live service, go live (members are told) and end it', function () {
    Notification::fake();
    [$church, $admin, $follower] = adminChurch();
    actAs($admin);

    $stream = $this->postJson(route('api.v1.admin.live.store', $church->slug), ['title' => 'Sunday Service', 'scheduled_at' => now()->addHour()->toDateTimeString(), 'source_type' => 'youtube', 'external_url' => 'https://youtu.be/dQw4w9WgXcQ', 'chat_enabled' => true])->assertCreated()->json('data');
    expect($stream['status'])->toBe('scheduled');
    $this->postJson(route('api.v1.admin.live.store', $church->slug), ['title' => 'No link', 'scheduled_at' => now()->addHour()->toDateTimeString(), 'source_type' => 'youtube'])->assertUnprocessable();

    $this->postJson(route('api.v1.admin.live.start', [$church->slug, $stream['id']]))->assertOk()->assertJsonPath('data.status', 'live');
    Notification::assertSentTo($follower, LiveStreamStarted::class);

    actAs($follower);
    $watch = $this->getJson(route('api.v1.live.show', $stream['id']))->assertOk()->json('data');
    expect($watch['status'])->toBe('live')->and($watch['playback']['embed'])->toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ');

    actAs($admin);
    $this->postJson(route('api.v1.admin.live.stop', [$church->slug, $stream['id']]))->assertOk()->assertJsonPath('data.status', 'ended');
    expect($this->getJson(route('api.v1.admin.live.index', $church->slug))->json('data.0.status'))->toBe('ended');
});

test('treasurers see what came in this week, month and year, by category and method', function () {
    [$church, , $follower] = adminChurch();
    $treasurer = staffWith($church, ['giving.view']);
    foreach ([[10000, 'tithe', 'mtn_momo'], [5000, 'offering', 'mtn_momo'], [20000, 'tithe', 'stripe']] as [$amount, $category, $method]) {
        $church->donations()->create(['user_id' => $follower->id, 'category' => $category, 'amount' => $amount, 'method' => $method, 'status' => 'succeeded', 'paid_at' => now()]);
    }
    $church->donations()->create(['user_id' => $follower->id, 'category' => 'tithe', 'amount' => 999, 'method' => 'mtn_momo', 'status' => 'failed']);
    $church->donations()->create(['user_id' => $follower->id, 'category' => 'tithe', 'amount' => 700, 'method' => 'mtn_momo', 'status' => 'succeeded', 'paid_at' => now()->subYears(2)]);

    actAs($treasurer);
    $s = $this->getJson(route('api.v1.admin.giving.summary', $church->slug))->assertOk()->json('data');
    expect($s['month'])->toEqual(35000)->and($s['year'])->toEqual(35000)->and($s['gifts_month'])->toBe(3)
        ->and($s['by_category'][0])->toMatchArray(['key' => 'tithe', 'total' => 30000.0])->and($s['by_method'][0]['key'])->toBe('stripe');
    $list = $this->getJson(route('api.v1.admin.giving.index', $church->slug))->assertOk()->json('data');
    expect($list)->toHaveCount(4)->and($list[0])->toHaveKeys(['reference', 'giver', 'amount', 'status', 'category', 'method']);
    $this->getJson(route('api.v1.admin.members.index', $church->slug))->assertForbidden();
});

test('moderators hide or dismiss what members reported', function () {
    [$church, $admin, $follower] = adminChurch();
    $writer = member();
    $church->subscriptions()->create(['user_id' => $writer->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);
    $post = $church->posts()->create(['user_id' => $writer->id, 'title' => 'Update', 'body' => 'Hello', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()]);
    $comment = Comment::create(['church_id' => $church->id, 'commentable_type' => $post->getMorphClass(), 'commentable_id' => $post->id, 'user_id' => $writer->id, 'body' => 'Rude words']);
    $report = app(ModerationService::class)->report($follower, $comment, ['reason' => 'abuse']);
    $keep = app(ModerationService::class)->report($follower, $post, ['reason' => 'spam']);

    actAs($admin);
    $open = $this->getJson(route('api.v1.admin.reports.index', $church->slug))->assertOk()->json('data');
    expect($open)->toHaveCount(2)->and(collect($open)->pluck('reason')->sort()->values()->all())->toBe(['abuse', 'spam'])->and(collect($open)->firstWhere('reason', 'abuse')['content'])->toBe('Rude words');

    $this->postJson(route('api.v1.admin.reports.hide', [$church->slug, $report->id]))->assertOk();
    $this->postJson(route('api.v1.admin.reports.dismiss', [$church->slug, $keep->id]))->assertOk()->assertJsonPath('status', 'dismissed');
    expect($comment->fresh()->status)->toBe('hidden')->and(ContentReport::where('status', 'open')->count())->toBe(0);
    expect($this->getJson(route('api.v1.admin.reports.index', $church->slug))->json('data'))->toBe([]);
});
