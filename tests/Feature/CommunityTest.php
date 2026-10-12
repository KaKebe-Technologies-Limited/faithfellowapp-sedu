<?php

use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Comment;
use App\Models\CounselingAppointment;
use App\Models\PastoralNote;
use App\Models\PrayerInteraction;
use App\Models\PrayerRequest;
use App\Models\Reaction;
use App\Models\SmallGroup;
use App\Models\SmallGroupAttendance;
use App\Models\Testimony;
use App\Models\User;
use App\Models\VolunteerRota;
use App\Notifications\Care\CareDigest;
use App\Notifications\Care\CommunityUpdate;
use App\Notifications\Content\AnnouncementPublished;
use App\Notifications\Prayer\PrayerAnswered;
use App\Notifications\Prayer\PrayerChainRequest;
use App\Notifications\Prayer\PrayerRequestSubmitted;
use App\Services\CareService;
use App\Services\FeedService;
use App\Services\PrayerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/** @return array{0: Church, 1: User, 2: User, 3: User} church, admin, member, another member */
function communityChurch(array $attributes = []): array
{
    $admin = member();
    $church = Church::factory()->create($attributes);
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);
    $a = member();
    $b = member();
    foreach ([$a, $b] as $u) {
        $church->subscriptions()->create(['user_id' => $u->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);
    }

    return [$church, $admin, $a, $b];
}

function prayerFor(Church $church, User $author, array $attributes = []): PrayerRequest
{
    return $church->prayerRequests()->create([
        'user_id' => $author->id, 'body' => 'Please pray for my family.', 'visibility' => 'church', 'status' => 'open', ...$attributes,
    ]);
}

/*
|--------------------------------------------------------------------------
| Prayer wall
|--------------------------------------------------------------------------
*/

test('member shares a prayer request and the pastoral team is notified', function () {
    Notification::fake();
    [$church, $admin, $member] = communityChurch();

    $this->actingAs($member)->post(route('prayer.store'), [
        'church_id' => $church->id, 'body' => 'Healing for my mother in hospital.', 'visibility' => 'church',
    ])->assertRedirect();

    $prayer = PrayerRequest::firstOrFail();
    expect($prayer->user_id)->toBe($member->id)->and($prayer->church_id)->toBe($church->id);
    Notification::assertSentTo($admin, PrayerRequestSubmitted::class);
    Notification::assertNotSentTo($member, PrayerRequestSubmitted::class);
});

test('members cannot post prayer requests to churches they do not belong to', function () {
    [, , $member] = communityChurch();
    $other = Church::factory()->create();

    $this->actingAs($member)->post(route('prayer.store'), ['church_id' => $other->id, 'body' => 'Hello there', 'visibility' => 'public'])
        ->assertSessionHasErrors('church_id');
});

test('prayer visibility: church-only for members, pastor-only for pastoral staff', function () {
    [$church, $admin, $author, $fellow] = communityChurch();
    $outsider = member();
    $churchOnly = prayerFor($church, $author);
    $pastorOnly = prayerFor($church, $author, ['visibility' => 'pastor', 'body' => 'Private family matter']);

    $this->actingAs($fellow)->get(route('prayer.show', $churchOnly))->assertOk();
    $this->actingAs($outsider)->get(route('prayer.show', $churchOnly))->assertForbidden();
    $this->actingAs($fellow)->get(route('prayer.show', $pastorOnly))->assertForbidden();
    $this->actingAs($author)->get(route('prayer.show', $pastorOnly))->assertOk();
    $this->actingAs($admin)->get(route('prayer.show', $pastorOnly))->assertOk();

    $this->actingAs($fellow)->get(route('prayer.index'))->assertOk()
        ->assertSee('Please pray for my family.')->assertDontSee('Private family matter');
});

test('"I prayed" toggles once per person and keeps the count in sync', function () {
    [$church, , $author, $fellow] = communityChurch();
    $prayer = prayerFor($church, $author);

    $this->actingAs($fellow)->postJson(route('prayer.pray', $prayer))->assertOk()->assertJson(['prayed' => true, 'count' => 1]);
    $this->actingAs($fellow)->postJson(route('prayer.pray', $prayer))->assertOk()->assertJson(['prayed' => false, 'count' => 0]);
    $this->actingAs($fellow)->postJson(route('prayer.pray', $prayer))->assertJson(['prayed' => true, 'count' => 1]);

    expect($prayer->fresh()->prayed_count)->toBe(1);
    $this->actingAs(member())->postJson(route('prayer.pray', $prayer))->assertForbidden();
});

test('prayer chains ask one member at a time and move on when they pray', function () {
    Notification::fake();
    [$church, $admin, $author, $fellow] = communityChurch();
    $third = member();
    $church->subscriptions()->create(['user_id' => $third->id, 'status' => 'active', 'subscribed_at' => now()]);

    $prayer = app(PrayerService::class)->submit($author, $church, ['body' => 'Pray for rain', 'visibility' => 'church', 'chain_enabled' => true]);
    expect($prayer->status)->toBe('pending')
        ->and(PrayerInteraction::where('type', 'chain')->count())->toBe(0); // the chain waits for a pastor's approval

    app(PrayerService::class)->approve($prayer, $admin);

    $first = PrayerInteraction::where('type', 'chain')->sole();
    expect($first->user_id)->not->toBe($author->id);
    Notification::assertSentTo(User::find($first->user_id), PrayerChainRequest::class);

    $this->actingAs(User::find($first->user_id))->postJson(route('prayer.pray', $prayer))->assertOk();

    expect($first->fresh()->completed_at)->not->toBeNull()
        ->and(PrayerInteraction::where('type', 'chain')->count())->toBe(2);
});

test('pastor-only requests never start a prayer chain', function () {
    Notification::fake();
    [$church, , $author] = communityChurch();

    app(PrayerService::class)->submit($author, $church, ['body' => 'Private', 'visibility' => 'pastor', 'chain_enabled' => true]);

    expect(PrayerInteraction::where('type', 'chain')->count())->toBe(0);
});

test('marking a prayer answered tells everyone who prayed', function () {
    Notification::fake();
    [$church, , $author, $fellow] = communityChurch();
    $prayer = prayerFor($church, $author);
    app(PrayerService::class)->togglePrayed($prayer, $fellow);

    $this->actingAs($fellow)->post(route('prayer.answered', $prayer))->assertForbidden();
    $this->actingAs($author)->post(route('prayer.answered', $prayer), ['answer_note' => 'She is home!'])->assertRedirect();

    expect($prayer->fresh()->status)->toBe('answered');
    Notification::assertSentTo($fellow, PrayerAnswered::class);
});

test('moderators hide prayer requests; staff without the permission cannot', function () {
    [$church, $admin, $author, $fellow] = communityChurch();
    $prayer = prayerFor($church, $author, ['body' => 'Inappropriate text']);
    $finance = member();
    $role = $church->roles()->create(['name' => 'Finance', 'slug' => 'finance', 'permissions' => ['giving.view']]);
    $church->staffMembers()->create(['user_id' => $finance->id, 'church_role_id' => $role->id, 'status' => 'active']);

    $this->actingAs($finance)->get(route('church-admin.prayer.index', $church))->assertForbidden();
    $this->actingAs($admin)->put(route('church-admin.prayer.update', [$church, $prayer]), ['status' => 'hidden'])->assertRedirect();

    expect($prayer->fresh()->status)->toBe('hidden');
    $this->actingAs($fellow)->get(route('prayer.index'))->assertDontSee('Inappropriate text');
});

test('church admins cannot moderate another church’s prayers (scoped bindings)', function () {
    [$churchA, $adminA] = communityChurch();
    [$churchB, , $authorB] = communityChurch();
    $prayer = prayerFor($churchB, $authorB);

    $this->actingAs($adminA)->put(route('church-admin.prayer.update', [$churchA, $prayer]), ['status' => 'hidden'])->assertNotFound();
    expect($prayer->fresh()->status)->toBe('open');
});

/*
|--------------------------------------------------------------------------
| Testimonies, reactions, comments, feed
|--------------------------------------------------------------------------
*/

test('testimonies are reviewed before they appear', function () {
    Notification::fake();
    [$church, $admin, $author, $fellow] = communityChurch();

    $this->actingAs($author)->post(route('testimonies.store'), [
        'church_id' => $church->id, 'title' => 'God provided', 'body' => 'A long story of God’s faithfulness to our family this year.',
    ])->assertRedirect(route('testimonies.index'));

    $testimony = Testimony::sole();
    expect($testimony->status)->toBe('pending');
    Notification::assertSentTo($admin, CommunityUpdate::class);
    $this->actingAs($fellow)->get(route('testimonies.index'))->assertDontSee('God provided');

    $this->actingAs($admin)->put(route('church-admin.testimonies.update', [$church, $testimony]), ['status' => 'approved'])->assertRedirect();

    $this->actingAs($fellow)->get(route('testimonies.index'))->assertSee('God provided');
    Notification::assertSentTo($author, CommunityUpdate::class);
});

test('reactions toggle and comments post on church content', function () {
    [$church, , $author, $fellow] = communityChurch();
    $post = $church->posts()->create(['body' => 'Sunday was amazing', 'visibility' => 'members', 'status' => 'published', 'published_at' => now()]);

    $this->actingAs($fellow)->postJson(route('reactions.toggle', ['post', $post->id]), ['type' => 'amen'])
        ->assertOk()->assertJson(['mine' => 'amen', 'counts' => ['amen' => 1]]);
    $this->actingAs($fellow)->postJson(route('reactions.toggle', ['post', $post->id]), ['type' => 'love'])->assertJson(['mine' => 'love']);
    expect(Reaction::count())->toBe(1);
    $this->actingAs($fellow)->postJson(route('reactions.toggle', ['post', $post->id]), ['type' => 'love'])->assertJson(['mine' => null]);
    expect(Reaction::count())->toBe(0);

    $this->actingAs($fellow)->post(route('comments.store', ['post', $post->id]), ['body' => 'Amen <b>!</b>'])->assertRedirect();
    expect(Comment::sole()->body)->toBe('Amen !');

    // Members-only post: outsiders cannot react or comment.
    $this->actingAs(member())->postJson(route('reactions.toggle', ['post', $post->id]), ['type' => 'amen'])->assertForbidden();
    $this->actingAs(member())->post(route('comments.store', ['post', $post->id]), ['body' => 'hi'])->assertForbidden();
});

test('comments on pastor-only prayers are blocked for other members', function () {
    [$church, , $author, $fellow] = communityChurch();
    $prayer = prayerFor($church, $author, ['visibility' => 'pastor']);

    $this->actingAs($fellow)->post(route('comments.store', ['prayer', $prayer->id]), ['body' => 'Praying'])->assertForbidden();
    $this->actingAs($author)->post(route('comments.store', ['prayer', $prayer->id]), ['body' => 'Thank you'])->assertRedirect();
});

test('the home feed includes prayer requests and testimonies but never pastor-only requests', function () {
    [$church, , $author, $fellow] = communityChurch();
    // The feed cursor is "strictly before now", so backdate the items.
    prayerFor($church, $author, ['body' => 'Open request'])->forceFill(['created_at' => now()->subMinute()])->save();
    prayerFor($church, $author, ['body' => 'Secret request', 'visibility' => 'pastor'])->forceFill(['created_at' => now()->subMinute()])->save();
    $church->testimonies()->create(['user_id' => $author->id, 'title' => 'Healed', 'body' => 'Story', 'status' => 'approved', 'reviewed_at' => now()->subMinute()]);

    $types = app(FeedService::class)->forUser($fellow)['items']->map(fn ($i) => $i['type'].':'.($i['model']->body ?? ''));

    expect($types)->toContain('prayer:Open request')->toContain('testimony:Story')->not->toContain('prayer:Secret request');
    $this->actingAs($fellow)->get(route('dashboard'))->assertOk()->assertSee('Open request');
});

/*
|--------------------------------------------------------------------------
| Small groups
|--------------------------------------------------------------------------
*/

test('admins create groups; members ask to join; leaders approve and take attendance', function () {
    Notification::fake();
    [$church, $admin, $leader, $joiner] = communityChurch();

    $this->actingAs($admin)->post(route('church-admin.small-groups.store', $church), [
        'name' => 'Ireda Cell', 'type' => 'cell', 'leader_id' => $leader->id, 'meeting_day' => 2, 'meeting_time' => '18:00', 'is_open' => 1, 'status' => 'active',
    ])->assertRedirect();
    $group = SmallGroup::sole();
    expect($group->memberships()->where('user_id', $leader->id)->value('role'))->toBe('leader');

    $this->actingAs($joiner)->post(route('groups.join', $group))->assertRedirect();
    $membership = $group->memberships()->where('user_id', $joiner->id)->sole();
    expect($membership->status)->toBe('pending');
    Notification::assertSentTo($leader, CommunityUpdate::class);

    // A regular member cannot use leader tools.
    $this->actingAs($joiner)->put(route('groups.members.update', [$group, $membership]), ['action' => 'approve'])->assertForbidden();
    $this->actingAs($leader)->put(route('groups.members.update', [$group, $membership]), ['action' => 'approve'])->assertRedirect();
    expect($membership->fresh()->status)->toBe('active');

    $this->actingAs($joiner)->post(route('groups.meetings.store', $group), ['meeting_date' => today()->toDateString()])->assertForbidden();
    $this->actingAs($leader)->post(route('groups.meetings.store', $group), [
        'meeting_date' => today()->toDateString(), 'topic' => 'James 1', 'present' => [$leader->id],
    ])->assertRedirect();

    expect(SmallGroupAttendance::where('user_id', $leader->id)->value('status'))->toBe('present')
        ->and(SmallGroupAttendance::where('user_id', $joiner->id)->value('status'))->toBe('absent');
});

test('group announcements reach only active group members', function () {
    Notification::fake();
    [$church, , $leader, $outsideGroup] = communityChurch();
    $group = $church->smallGroups()->create(['name' => 'Youth', 'type' => 'youth', 'leader_id' => $leader->id]);
    $group->memberships()->create(['church_id' => $church->id, 'user_id' => $leader->id, 'role' => 'leader', 'status' => 'active']);
    $inGroup = member();
    $church->subscriptions()->create(['user_id' => $inGroup->id, 'status' => 'active', 'subscribed_at' => now()]);
    $group->memberships()->create(['church_id' => $church->id, 'user_id' => $inGroup->id, 'status' => 'active']);

    $this->actingAs($leader)->post(route('groups.announcements.store', $group), ['title' => 'No meeting Tuesday', 'body' => 'See you Friday'])->assertRedirect();

    Notification::assertSentTo($inGroup, AnnouncementPublished::class);
    Notification::assertNotSentTo($outsideGroup, AnnouncementPublished::class);
});

/*
|--------------------------------------------------------------------------
| Attendance & volunteers
|--------------------------------------------------------------------------
*/

test('QR check-in via the signed link records attendance once', function () {
    [$church, , $member] = communityChurch();
    $url = URL::temporarySignedRoute('check-in.show', today()->endOfDay(), ['church' => $church]);

    $this->actingAs($member)->get($url)->assertOk()->assertSee(__('Check me in'));
    $this->actingAs($member)->post($url)->assertRedirect();
    $this->actingAs($member)->post($url)->assertRedirect();

    expect(AttendanceRecord::where('user_id', $member->id)->count())->toBe(1)
        ->and(AttendanceRecord::value('method'))->toBe('qr');
});

test('geofence check-in only works near the church', function () {
    [$church, , $member] = communityChurch(['lat' => 2.2475, 'lng' => 32.9001]);

    $this->actingAs($member)->postJson(route('check-in.store', $church), ['lat' => 0.3476, 'lng' => 32.5825])->assertStatus(422);
    $this->actingAs($member)->postJson(route('check-in.store', $church), ['lat' => 2.2476, 'lng' => 32.9002])->assertOk();

    expect(AttendanceRecord::where('user_id', $member->id)->value('method'))->toBe('geofence');
});

test('volunteers are notified, confirm their slot and get one reminder', function () {
    Notification::fake();
    [$church, $admin, $volunteer] = communityChurch();
    $team = $church->volunteerTeams()->create(['name' => 'Ushers']);

    $this->actingAs($admin)->post(route('church-admin.volunteers.rota.store', $church), [
        'volunteer_team_id' => $team->id, 'user_id' => $volunteer->id, 'service_date' => today()->addDay()->toDateString(),
    ])->assertRedirect();
    $rota = VolunteerRota::sole();
    Notification::assertSentTo($volunteer, CommunityUpdate::class);

    $this->actingAs(member())->put(route('volunteering.respond', $rota), ['status' => 'confirmed'])->assertForbidden();
    $this->actingAs($volunteer)->put(route('volunteering.respond', $rota), ['status' => 'confirmed'])->assertRedirect();
    expect($rota->fresh()->status)->toBe('confirmed');

    expect(app(CareService::class)->remindVolunteers())->toBe(1)
        ->and(app(CareService::class)->remindVolunteers())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Pastoral care & counseling (confidential)
|--------------------------------------------------------------------------
*/

test('counseling topics are encrypted and hidden from platform super admins', function () {
    Notification::fake();
    [$church, $admin, $member, $other] = communityChurch();

    $this->actingAs($member)->post(route('counseling.store'), [
        'church_id' => $church->id, 'topic' => 'Struggling with grief', 'mode' => 'phone',
    ])->assertRedirect();
    $appointment = CounselingAppointment::sole();

    expect(DB::table('counseling_appointments')->value('topic'))->not->toContain('grief')
        ->and($appointment->topic)->toBe('Struggling with grief')
        ->and($appointment->toArray())->not->toHaveKey('topic');

    expect($admin->can('viewNotes', $appointment))->toBeTrue()
        ->and(superAdmin()->can('viewNotes', $appointment))->toBeFalse()
        ->and($other->can('view', $appointment))->toBeFalse();

    $this->actingAs($admin)->get(route('church-admin.counseling.index', $church))->assertOk()->assertSee('Struggling with grief');

    $this->actingAs($other)->post(route('counseling.cancel', $appointment))->assertForbidden();
    $this->actingAs($member)->post(route('counseling.cancel', $appointment))->assertRedirect();
    expect($appointment->fresh()->status)->toBe('cancelled');
});

test('super admins still pass other authorization checks', function () {
    [$church, , $author] = communityChurch();
    $prayer = prayerFor($church, $author, ['visibility' => 'pastor']);

    expect(superAdmin()->can('view', $prayer))->toBeTrue();
});

test('pastoral notes are encrypted and private notes are visible only to their author', function () {
    [$church, $admin, $member] = communityChurch();
    $colleague = member();
    $role = $church->roles()->create(['name' => 'Care', 'slug' => 'care', 'permissions' => ['care.manage']]);
    $church->staffMembers()->create(['user_id' => $colleague->id, 'church_role_id' => $role->id, 'status' => 'active']);

    $this->actingAs($admin)->post(route('church-admin.care.notes.store', $church), ['user_id' => $member->id, 'body' => 'Lost her job', 'is_private' => 1])->assertRedirect();
    $this->actingAs($admin)->post(route('church-admin.care.notes.store', $church), ['user_id' => $member->id, 'body' => 'Wants baptism', 'is_private' => 0])->assertRedirect();

    expect(DB::table('pastoral_notes')->pluck('body')->implode(' '))->not->toContain('Lost her job')
        ->and(PastoralNote::where('is_private', true)->value('body'))->toBe('Lost her job');

    $this->actingAs($colleague)->get(route('church-admin.care.member', [$church, $member->id]))->assertOk()
        ->assertSee('Wants baptism')->assertDontSee('Lost her job');
    $this->actingAs($admin)->get(route('church-admin.care.member', [$church, $member->id]))->assertSee('Lost her job');

    // Members without care.manage never reach the care dashboard.
    $this->actingAs($member)->get(route('church-admin.care.index', $church))->assertForbidden();
});

test('the weekly care digest goes to pastors when there is something to follow up', function () {
    Notification::fake();
    [$church, $admin, $member] = communityChurch();
    $member->forceFill(['date_of_birth' => now()->subYears(30)->addDays(2)])->save();

    expect(app(CareService::class)->sendWeeklyDigests())->toBeGreaterThan(0);
    Notification::assertSentTo($admin, CareDigest::class);
});

test('community pages render for members and church admins', function () {
    [$church, $admin, $member] = communityChurch();
    $group = $church->smallGroups()->create(['name' => 'Cell', 'type' => 'cell', 'leader_id' => $member->id, 'lat' => 2.25, 'lng' => 32.9]);
    $group->memberships()->create(['church_id' => $church->id, 'user_id' => $member->id, 'role' => 'leader', 'status' => 'active']);

    foreach (['prayer.index', 'prayer.create', 'testimonies.index', 'testimonies.create', 'groups.index', 'counseling.index', 'volunteering.index'] as $route) {
        $this->actingAs($member)->get(route($route))->assertOk();
    }
    $this->actingAs($member)->get(route('groups.show', $group))->assertOk()->assertSee(__('Record a meeting'));
    $this->actingAs($member)->get(route('churches.show', [$church, 'tab' => 'prayer']))->assertOk();

    foreach (['prayer.index', 'testimonies.index', 'comments.index', 'small-groups.index', 'small-groups.create', 'volunteers.index', 'attendance.index', 'attendance.qr', 'care.index', 'counseling.index'] as $route) {
        $this->actingAs($admin)->get(route('church-admin.'.$route, $church))->assertOk();
    }
});
