<?php

use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Comment;
use App\Models\ContentReport;
use App\Models\PlatformSetting;
use App\Models\Podcast;
use App\Models\RadioStation;
use App\Models\RadioTrack;
use App\Models\User;
use App\Services\ModerationService;
use Laravel\Sanctum\Sanctum;

/*
| The platform admin runs FaithFellows from the app with the same sign-in as on the website: what needs attention, church applications, suspending a church,
| putting people into churches, reports, podcast reviews, and what goes out to everyone. Only super admins can; what is hard to undo asks for the password again.
*/

function platformAdmin(): User
{
    $admin = superAdmin();
    Sanctum::actingAs($admin, ['member']);

    return $admin;
}

test('only the platform admin reaches any of it', function () {
    $church = Church::factory()->create();
    $staff = member();
    $church->staffMembers()->create(['user_id' => $staff->id, 'is_admin' => true, 'status' => 'active']);

    foreach ([member(), $staff] as $who) {
        Sanctum::actingAs($who, ['member']);
        $this->getJson(route('api.v1.platform.overview'))->assertForbidden();
        $this->getJson(route('api.v1.platform.churches'))->assertForbidden();
        $this->postJson(route('api.v1.platform.churches.approve', $church))->assertForbidden();
        $this->getJson(route('api.v1.platform.users'))->assertForbidden();
        $this->postJson(route('api.v1.platform.announce'), ['title' => 'Hi', 'body' => 'All', 'password' => 'password'])->assertForbidden();
        $this->deleteJson(route('api.v1.platform.radio.broadcast.stop'), ['password' => 'password'])->assertForbidden();
    }
    $this->app['auth']->forgetGuards();
    $this->getJson(route('api.v1.platform.overview'))->assertUnauthorized();
});

test('the overview counts churches, people and what is waiting', function () {
    Church::factory()->create(['status' => 'pending']);
    Church::factory()->count(2)->create(['status' => 'active']);
    Church::factory()->create(['status' => 'suspended']);
    $writer = member();
    $church = Church::factory()->create();
    $comment = Comment::create(['church_id' => $church->id, 'commentable_type' => $church->getMorphClass(), 'commentable_id' => $church->id, 'user_id' => $writer->id, 'body' => 'Rude words']);
    app(ModerationService::class)->report(member(), $comment, ['reason' => 'abuse']);
    Podcast::create(['user_id' => $writer->id, 'name' => 'Waiting', 'host_name' => 'Host', 'summary' => 'A short word every day for you.', 'category' => 'devotional', 'language' => 'en', 'status' => 'pending']);

    platformAdmin();
    $data = $this->getJson(route('api.v1.platform.overview'))->assertOk()->json('data');

    expect($data['churches'])->toMatchArray(['pending' => 1, 'active' => 3, 'suspended' => 1, 'archived' => 0])
        ->and($data['needs_attention'])->toMatchArray(['applications' => 1, 'podcasts' => 1, 'reports' => 1])
        ->and($data['users'])->toBeGreaterThanOrEqual(3)->and($data['broadcast'])->toBeNull();
});

test('an application is approved, or declined with the password, and only while it is waiting', function () {
    $pending = Church::factory()->create(['name' => 'Hope Chapel', 'status' => 'pending']);
    $another = Church::factory()->create(['name' => 'Second Chapel', 'status' => 'pending']);
    $admin = platformAdmin();

    $list = $this->getJson(route('api.v1.platform.churches', ['status' => 'pending']))->assertOk()->json('data');
    expect(collect($list)->pluck('name')->sort()->values()->all())->toBe(['Hope Chapel', 'Second Chapel'])->and($list[0])->toHaveKeys(['id', 'slug', 'status', 'owner', 'followers']);

    $this->postJson(route('api.v1.platform.churches.approve', $pending))->assertOk()->assertJsonPath('data.status', 'active');
    expect($pending->fresh()->status)->toBe('active')->and($pending->fresh()->approved_by)->toBe($admin->id);
    $this->postJson(route('api.v1.platform.churches.approve', $pending))->assertUnprocessable(); // it is not waiting any more

    $this->postJson(route('api.v1.platform.churches.decline', $another), ['reason' => 'Duplicate'])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson(route('api.v1.platform.churches.decline', $another), ['reason' => 'Duplicate', 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect($another->fresh()->status)->toBe('pending');
    $this->postJson(route('api.v1.platform.churches.decline', $another), ['reason' => 'Duplicate', 'password' => 'password'])->assertOk()->assertJsonPath('data.status', 'archived');
    expect($another->fresh()->status_reason)->toBe('Duplicate');
});

test('suspending and bringing back a church asks for the password and is recorded', function () {
    $church = Church::factory()->create(['name' => 'Grace Church', 'status' => 'active']);
    platformAdmin();

    $this->postJson(route('api.v1.platform.churches.suspend', $church), ['reason' => 'Unpaid'])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect($church->fresh()->status)->toBe('active');

    $this->postJson(route('api.v1.platform.churches.suspend', $church), ['reason' => 'Unpaid', 'password' => 'password'])->assertOk()->assertJsonPath('data.status', 'suspended')->assertJsonPath('data.status_reason', 'Unpaid');
    $this->postJson(route('api.v1.platform.churches.suspend', $church), ['password' => 'password'])->assertUnprocessable(); // already suspended
    expect(AuditLog::where('action', 'church.suspended')->count())->toBe(1);

    $this->postJson(route('api.v1.platform.churches.reactivate', $church), [])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson(route('api.v1.platform.churches.reactivate', $church), ['password' => 'password'])->assertOk()->assertJsonPath('data.status', 'active');
    expect(AuditLog::where('action', 'church.reactivated')->count())->toBe(1);
});

test('people are found by name, email or phone and put into churches', function () {
    $first = Church::factory()->create(['name' => 'Hope Chapel']);
    $second = Church::factory()->create(['name' => 'Grace Church']);
    $ann = member(['name' => 'Ann Newcomer', 'email' => 'ann@example.test', 'phone' => '+256772123456']);
    member(['name' => 'Someone Else']);
    $suspended = member(['name' => 'Sam Suspended', 'status' => 'suspended']);
    $admin = platformAdmin();

    $found = $this->getJson(route('api.v1.platform.users', ['q' => 'newcomer']))->assertOk()->json('data');
    expect($found)->toHaveCount(1)->and($found[0])->toMatchArray(['name' => 'Ann Newcomer', 'email' => 'ann@example.test', 'phone' => '+256772123456', 'churches' => []]);
    expect($this->getJson(route('api.v1.platform.users', ['q' => '772123']))->json('data.0.name'))->toBe('Ann Newcomer');

    $this->postJson(route('api.v1.platform.users.assign', $ann), [])->assertUnprocessable()->assertJsonValidationErrors('church_ids');
    $this->postJson(route('api.v1.platform.users.assign', $ann), ['church_ids' => [$first->id, $second->id], 'notify' => false])->assertOk()
        ->assertJsonPath('data.0.church', 'Grace Church')->assertJsonCount(2, 'data');
    expect($ann->subscribedChurches()->pluck('churches.name')->sort()->values()->all())->toBe(['Grace Church', 'Hope Chapel']);
    expect($this->getJson(route('api.v1.platform.users', ['q' => 'Ann']))->json('data.0.churches'))->toHaveCount(2);

    $this->postJson(route('api.v1.platform.users.assign', $suspended), ['church_ids' => [$first->id]])->assertUnprocessable();
    expect($admin->id)->not->toBe($ann->id);
});

test('reports are read and hidden, dismissed, or end in suspending the author with the password', function () {
    $church = Church::factory()->create(['name' => 'Grace Church']);
    $writer = member(['name' => 'Rude Writer']);
    $post = $church->posts()->create(['user_id' => $writer->id, 'title' => 'Update', 'body' => 'Hello', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()]);
    $one = Comment::create(['church_id' => $church->id, 'commentable_type' => $post->getMorphClass(), 'commentable_id' => $post->id, 'user_id' => $writer->id, 'body' => 'Rude words one']);
    $two = Comment::create(['church_id' => $church->id, 'commentable_type' => $post->getMorphClass(), 'commentable_id' => $post->id, 'user_id' => $writer->id, 'body' => 'Fine words two']);
    $three = Comment::create(['church_id' => $church->id, 'commentable_type' => $post->getMorphClass(), 'commentable_id' => $post->id, 'user_id' => $writer->id, 'body' => 'Rude words three']);
    $reporter = member();
    $reports = collect([$one, $two, $three])->map(fn ($c) => app(ModerationService::class)->report($reporter, $c, ['reason' => 'abuse']));
    platformAdmin();

    $list = $this->getJson(route('api.v1.platform.reports'))->assertOk()->json('data');
    expect($list)->toHaveCount(3)->and(collect($list)->pluck('excerpt')->sort()->values()->all())->toBe(['Fine words two', 'Rude words one', 'Rude words three'])
        ->and($list[0])->toMatchArray(['kind' => 'comment', 'reason' => 'Harassment or abuse', 'church' => 'Grace Church'])->and($list[0]['author']['name'])->toBe('Rude Writer');

    $this->postJson(route('api.v1.platform.reports.handle', $reports[0]), ['action' => 'hide'])->assertOk();
    $this->postJson(route('api.v1.platform.reports.handle', $reports[1]), ['action' => 'dismiss'])->assertOk();
    expect($one->fresh()->status)->toBe('hidden')->and(ContentReport::where('status', 'open')->count())->toBe(1);
    $this->postJson(route('api.v1.platform.reports.handle', $reports[0]), ['action' => 'hide'])->assertUnprocessable(); // handled already

    $this->postJson(route('api.v1.platform.reports.handle', $reports[2]), ['action' => 'suspend'])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect($writer->fresh()->status)->toBe('active');
    $this->postJson(route('api.v1.platform.reports.handle', $reports[2]), ['action' => 'suspend', 'password' => 'password'])->assertOk();
    expect($writer->fresh()->status)->toBe('suspended')->and($three->fresh()->status)->toBe('hidden');
    expect($this->getJson(route('api.v1.platform.reports'))->json('data'))->toBe([]);
});

test('podcasts waiting for review are approved, turned down with a reason, or hidden', function () {
    $owner = member();
    $make = fn (string $name) => Podcast::create(['user_id' => $owner->id, 'name' => $name, 'host_name' => 'Host', 'summary' => 'A short word every day for you.', 'category' => 'devotional', 'language' => 'en', 'status' => 'pending']);
    [$a, $b, $c] = [$make('Alpha'), $make('Beta'), $make('Gamma')];
    platformAdmin();

    expect($this->getJson(route('api.v1.platform.podcasts'))->assertOk()->json('data'))->toHaveCount(3);
    $this->postJson(route('api.v1.platform.podcasts.review', $a), ['action' => 'approve'])->assertOk();
    $this->postJson(route('api.v1.platform.podcasts.review', $b), ['action' => 'reject'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson(route('api.v1.platform.podcasts.review', $b), ['action' => 'reject', 'reason' => 'Please add a cover'])->assertOk();
    $this->postJson(route('api.v1.platform.podcasts.review', $c), ['action' => 'hide'])->assertOk();

    expect($a->fresh()->status)->toBe('approved')->and($b->fresh()->status)->toBe('rejected')->and($b->fresh()->review_note)->toBe('Please add a cover')->and($c->fresh()->status)->toBe('hidden');
    expect($this->getJson(route('api.v1.platform.podcasts'))->json('data'))->toBe([])
        ->and($this->getJson(route('api.v1.platform.podcasts', ['status' => 'approved']))->json('data.0.name'))->toBe('Alpha');
});

test('a notification to everyone needs the password and is recorded', function () {
    platformAdmin();

    $this->postJson(route('api.v1.platform.announce'), ['title' => 'Revival week', 'body' => 'Join us all week.'])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson(route('api.v1.platform.announce'), ['title' => '', 'body' => 'x', 'password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('title');
    $this->postJson(route('api.v1.platform.announce'), ['title' => 'Revival week', 'body' => 'Join us all week.', 'password' => 'password'])->assertOk()->assertJsonStructure(['sent', 'message']);
    expect(AuditLog::where('action', 'platform.announced')->count())->toBe(1);
});

test('a song or teaching is put on every radio, and taken off again, with the password', function () {
    Cache::forget('platform_settings');
    $church = Church::factory()->create();
    $platform = RadioTrack::create(['station_id' => RadioStation::gospel()->id, 'title' => 'Amazing Grace', 'artist' => 'Choir', 'file_path' => 'radio/a.mp3', 'mime' => 'audio/mpeg', 'size' => 100, 'duration_seconds' => 200, 'position' => 1, 'is_active' => true]);
    $own = RadioTrack::create(['station_id' => RadioStation::forChurch($church)->id, 'title' => 'Church Song', 'file_path' => 'radio/b.mp3', 'mime' => 'audio/mpeg', 'size' => 100, 'duration_seconds' => 200, 'position' => 1, 'is_active' => true]);
    platformAdmin();

    $radio = $this->getJson(route('api.v1.platform.radio'))->assertOk()->json('data');
    expect(collect($radio['tracks'])->pluck('title')->all())->toBe(['Amazing Grace'])->and($radio['broadcast'])->toBeNull();

    $this->postJson(route('api.v1.platform.radio.broadcast'), ['track' => $platform->id])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson(route('api.v1.platform.radio.broadcast'), ['track' => $own->id, 'password' => 'password'])->assertNotFound(); // only the platform's own audio
    $this->postJson(route('api.v1.platform.radio.broadcast'), ['track' => $platform->id, 'message' => 'Special service', 'password' => 'password'])->assertOk();
    expect($this->getJson(route('api.v1.platform.radio'))->json('data.broadcast.title'))->toBe('Amazing Grace')
        ->and($this->getJson(route('api.v1.platform.overview'))->json('data.broadcast.message'))->toBe('Special service');

    $this->deleteJson(route('api.v1.platform.radio.broadcast.stop'), [])->assertUnprocessable();
    $this->deleteJson(route('api.v1.platform.radio.broadcast.stop'), ['password' => 'password'])->assertOk();
    expect($this->getJson(route('api.v1.platform.radio'))->json('data.broadcast'))->toBeNull()->and(PlatformSetting::get('radio_broadcast'))->toBeEmpty();
});
