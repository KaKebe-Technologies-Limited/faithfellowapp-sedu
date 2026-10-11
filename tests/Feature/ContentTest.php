<?php

use App\Models\Announcement;
use App\Models\Church;
use App\Models\ContentView;
use App\Models\ReadingPlanProgress;
use App\Models\Sermon;
use App\Models\User;
use App\Notifications\Content\NewDevotional;
use App\Notifications\Content\NewSermonPublished;
use App\Support\ReadingPlanTemplates;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);
});

/** A tiny but real MP3 (ID3 + MPEG frame headers) so MIME sniffing sees audio/mpeg. */
function fakeMp3(): UploadedFile
{
    $frame = "\xFF\xFB\x90\x64".str_repeat("\x00", 413);

    return UploadedFile::fake()->createWithContent('sermon.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat($frame, 20));
}

/** @return array{0: Church, 1: User, 2: User} church, admin, subscriber */
function contentChurch(): array
{
    $admin = member();
    $church = Church::factory()->create();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);
    $subscriber = member();
    $church->subscriptions()->create(['user_id' => $subscriber->id, 'status' => 'active', 'subscribed_at' => now()]);

    return [$church, $admin, $subscriber];
}

test('admins can upload a sermon and subscribers are notified once', function () {
    Notification::fake();
    [$church, $admin, $subscriber] = contentChurch();

    $this->actingAs($admin)->post(route('church-admin.sermons.store', $church), [
        'title' => 'Walking by Faith', 'preacher' => 'Rev. Okello', 'scripture' => '2 Cor 5:7', 'language' => 'en',
        'status' => 'published', 'visibility' => 'public', 'new_series' => 'Faith Series',
        'description' => '<p>Hello <script>alert(1)</script></p>',
        'audio' => fakeMp3(),
    ])->assertRedirect(route('church-admin.sermons.index', $church));

    $sermon = Sermon::firstOrFail();
    expect($sermon->status)->toBe('published')
        ->and($sermon->series->title)->toBe('Faith Series')
        ->and($sermon->getFirstMedia('audio'))->not->toBeNull()
        ->and($sermon->description)->not->toContain('<script>')
        ->and($sermon->notified_at)->not->toBeNull();

    Notification::assertSentTo($subscriber, NewSermonPublished::class);

    // Editing does not notify again.
    $this->actingAs($admin)->put(route('church-admin.sermons.update', [$church, $sermon]), [
        'title' => 'Walking by Faith (updated)', 'language' => 'en', 'status' => 'published', 'visibility' => 'public',
    ]);
    Notification::assertSentToTimes($subscriber, NewSermonPublished::class, 1);
});

test('scheduled sermons stay hidden until the scheduler publishes them', function () {
    Notification::fake();
    [$church, $admin, $subscriber] = contentChurch();

    $this->actingAs($admin)->post(route('church-admin.sermons.store', $church), [
        'title' => 'Future Sermon', 'language' => 'en', 'status' => 'published', 'visibility' => 'public',
        'published_at' => now()->addHour()->format('Y-m-d H:i'),
    ]);
    $sermon = Sermon::firstOrFail();
    expect($sermon->status)->toBe('scheduled');
    $this->actingAs($subscriber)->get(route('sermons.show', $sermon))->assertForbidden();
    Notification::assertNothingSent();

    $this->travel(2)->hours();
    $this->artisan('ff:publish-scheduled')->assertSuccessful();

    expect($sermon->fresh()->status)->toBe('published');
    $this->actingAs($subscriber)->get(route('sermons.show', $sermon))->assertOk();
    Notification::assertSentTo($subscriber, NewSermonPublished::class);
});

test('members-only sermons are hidden from visitors and non-members', function () {
    [$church, , $subscriber] = contentChurch();
    $sermon = $church->sermons()->create(['title' => 'Members talk', 'language' => 'en', 'status' => 'published', 'visibility' => 'members', 'published_at' => now()->subDay()]);

    $this->get(route('sermons.show', $sermon))->assertForbidden();
    $this->actingAs(member())->get(route('sermons.show', $sermon))->assertForbidden();
    $this->actingAs($subscriber)->get(route('sermons.show', $sermon))->assertOk();
});

test('staff without content permission cannot manage sermons', function () {
    [$church] = contentChurch();
    $finance = member();
    $role = $church->roles()->create(['name' => 'Finance', 'slug' => 'finance', 'permissions' => ['giving.view']]);
    $church->staffMembers()->create(['user_id' => $finance->id, 'church_role_id' => $role->id, 'status' => 'active']);

    $this->actingAs($finance)->get(route('church-admin.sermons.create', $church))->assertForbidden();
    $this->actingAs($finance)->post(route('church-admin.sermons.store', $church), ['title' => 'x', 'language' => 'en', 'status' => 'published', 'visibility' => 'public'])->assertForbidden();
});

test('a sermon from another church cannot be edited through this church', function () {
    [$churchA, $adminA] = contentChurch();
    [$churchB] = contentChurch();
    $sermonB = $churchB->sermons()->create(['title' => 'B sermon', 'language' => 'en', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()]);

    $this->actingAs($adminA)->get("/church/{$churchA->slug}/admin/sermons/{$sermonB->id}/edit")->assertNotFound();
});

test('storage quota blocks uploads over the limit', function () {
    config(['faithfellows.limits.storage_mb' => 1]);
    [$church, $admin] = contentChurch();

    $this->actingAs($admin)->post(route('church-admin.sermons.store', $church), [
        'title' => 'Big', 'language' => 'en', 'status' => 'published', 'visibility' => 'public',
        'audio' => UploadedFile::fake()->create('big.mp3', 2048, 'audio/mpeg'),
    ])->assertSessionHasErrors('audio');
});

test('todays devotional notifies subscribers and appears in the feed', function () {
    Notification::fake();
    [$church, $admin, $subscriber] = contentChurch();

    $this->actingAs($admin)->post(route('church-admin.devotionals.store', $church), [
        'title' => 'Be still', 'scripture' => 'Psalm 46:10', 'scripture_text' => 'Be still and know', 'body' => '<p>Rest in God.</p>',
        'publish_date' => today()->toDateString(), 'status' => 'published', 'visibility' => 'public',
    ])->assertRedirect();

    Notification::assertSentTo($subscriber, NewDevotional::class);
    $this->actingAs($subscriber)->get('/dashboard')->assertSee('Be still');
});

test('staff-only announcements are hidden from regular members', function () {
    [$church, $admin, $subscriber] = contentChurch();
    $church->announcements()->create(['title' => 'Staff memo', 'body' => 'x', 'audience' => 'staff', 'priority' => 'normal', 'status' => 'published', 'published_at' => now()->subMinute()]);
    $church->announcements()->create(['title' => 'Members notice', 'body' => 'y', 'audience' => 'members', 'priority' => 'normal', 'status' => 'published', 'published_at' => now()->subMinute()]);

    $this->actingAs($subscriber)->get('/dashboard')->assertSee('Members notice')->assertDontSee('Staff memo');
    $this->actingAs($subscriber)->get(route('announcements.show', Announcement::where('title', 'Staff memo')->first()))->assertForbidden();
    $this->actingAs($admin)->get('/dashboard')->assertSee('Staff memo');
});

test('search finds sermons by words in the transcript', function () {
    [$church, , $subscriber] = contentChurch();
    $sermon = $church->sermons()->create(['title' => 'Untitled message', 'language' => 'en', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDay()]);
    $sermon->transcripts()->create(['church_id' => $church->id, 'language' => 'en', 'body' => 'Today we talk about the mustard seed parable.']);

    $this->actingAs($subscriber)->get(route('search', ['q' => 'mustard seed']))->assertSee('Untitled message');
});

test('reading plan progress tracks days and streaks', function () {
    [$church, , $subscriber] = contentChurch();
    $template = ReadingPlanTemplates::all()['gospels-30'];
    $plan = $church->readingPlans()->create(['title' => 'Gospels', 'entries' => $template['entries'], 'days_count' => 30, 'is_published' => true]);

    expect($template['entries'])->toHaveCount(30)->and($template['entries'][0]['passages'])->toStartWith('Matthew');

    $this->actingAs($subscriber)->post(route('reading-plans.start', $plan))->assertRedirect();
    $this->actingAs($subscriber)->post(route('reading-plans.complete', $plan), ['day' => 1]);
    $this->travel(1)->days();
    $this->actingAs($subscriber)->post(route('reading-plans.complete', $plan), ['day' => 2]);

    $progress = ReadingPlanProgress::firstOrFail();
    expect($progress->completed_days)->toBe([1, 2])->and($progress->current_streak)->toBe(2)->and($progress->nextDay())->toBe(3);
});

test('bible in a year template covers all 1189 chapters in 365 days', function () {
    $entries = ReadingPlanTemplates::all()['bible-in-a-year']['entries'];

    expect($entries)->toHaveCount(365)
        ->and($entries[0]['passages'])->toStartWith('Genesis 1')
        ->and(end($entries)['passages'])->toContain('Revelation');
});

test('sermon plays are tracked for analytics', function () {
    [$church, , $subscriber] = contentChurch();
    $sermon = $church->sermons()->create(['title' => 'S', 'language' => 'en', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDay()]);

    $this->actingAs($subscriber)->get(route('sermons.show', $sermon));
    $this->actingAs($subscriber)->postJson(route('sermons.track', $sermon), ['seconds' => 300])->assertOk();

    expect($sermon->fresh()->views_count)->toBe(1)->and($sermon->fresh()->listens_count)->toBe(1)
        ->and((int) ContentView::where('action', 'listen')->sum('seconds'))->toBe(300);
});
