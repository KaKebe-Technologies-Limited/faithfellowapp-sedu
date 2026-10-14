<?php

use App\Models\Church;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/** A church the given member follows (or not, when $member is null). */
function homeChurch(string $name, ?User $member = null): Church
{
    $church = Church::factory()->create(['name' => $name, 'slug' => str($name)->slug()->toString()]);
    if ($member) {
        $church->subscriptions()->create(['user_id' => $member->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);
    }

    return $church;
}

function signedInMember(): User
{
    $member = member();
    Sanctum::actingAs($member, ['member']);

    return $member;
}

test('the home screen needs a signed-in member', function () {
    $this->getJson(route('api.v1.home'))->assertUnauthorized();
});

test('a member who follows no church gets an empty but well-formed home screen', function () {
    signedInMember();
    homeChurch('Somewhere Else'); // exists, but not followed

    $this->getJson(route('api.v1.home'))->assertOk()->assertExactJsonStructure([
        'data' => ['slides', 'highlights', 'inspiration', 'podcasts', 'churches', 'live', 'announcements', 'events', 'devotional', 'campaigns', 'sermons', 'top_sermons', 'verse', 'prayers', 'quiz'],
    ])->assertJsonPath('data.slides', [])->assertJsonPath('data.churches', [])->assertJsonPath('data.devotional', null);
});

test('the highlights come only from the churches the member follows and are current', function () {
    $member = signedInMember();
    $grace = homeChurch('Grace Lira', $member);
    $other = homeChurch('Not Followed');

    // Events: upcoming ones only, soonest first; members-only is fine because they follow the church.
    $grace->events()->create(['title' => 'Later event', 'starts_at' => now()->addDays(9), 'status' => 'published', 'visibility' => 'public']);
    $grace->events()->create(['title' => 'Soon event', 'starts_at' => now()->addDays(2), 'status' => 'published', 'visibility' => 'members']);
    $grace->events()->create(['title' => 'Finished event', 'starts_at' => now()->subDays(5), 'ends_at' => now()->subDays(5)->addHours(2), 'status' => 'published', 'visibility' => 'public']);
    $other->events()->create(['title' => 'Elsewhere event', 'starts_at' => now()->addDay(), 'status' => 'published', 'visibility' => 'public']);

    // Sermons: newest first, none from other churches.
    $grace->sermons()->create(['title' => 'Old sermon', 'language' => 'en', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDays(8)]);
    $grace->sermons()->create(['title' => 'New sermon', 'language' => 'en', 'status' => 'published', 'visibility' => 'members', 'published_at' => now()->subHour()]);
    $other->sermons()->create(['title' => 'Elsewhere sermon', 'language' => 'en', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()]);

    // Announcements: urgent first; staff-only ones are never shown.
    $grace->announcements()->create(['title' => 'Plain notice', 'body' => '<p>Bring a friend</p>', 'audience' => 'members', 'priority' => 'normal', 'status' => 'published', 'published_at' => now()->subMinutes(5)]);
    $grace->announcements()->create(['title' => 'Road closed', 'body' => 'Use the back gate', 'audience' => 'public', 'priority' => 'urgent', 'status' => 'published', 'published_at' => now()->subDay()]);
    $grace->announcements()->create(['title' => 'Staff memo', 'body' => 'x', 'audience' => 'staff', 'priority' => 'urgent', 'status' => 'published', 'published_at' => now()]);

    // Campaigns: active and not ended.
    $grace->campaigns()->create(['title' => 'Roof Fund', 'type' => 'building', 'goal_amount' => 1000000, 'raised_amount' => 250000, 'status' => 'active', 'visibility' => 'public']);
    $grace->campaigns()->create(['title' => 'Old Drive', 'type' => 'building', 'goal_amount' => 1000, 'status' => 'active', 'visibility' => 'public', 'ends_at' => now()->subDay()]);
    $other->campaigns()->create(['title' => 'Elsewhere Drive', 'type' => 'building', 'goal_amount' => 1000, 'status' => 'active', 'visibility' => 'public']);

    // Devotional: the latest from the last week.
    $grace->devotionals()->create(['title' => 'Stale', 'slug' => 'stale', 'body' => 'old', 'publish_date' => today()->subDays(12), 'status' => 'published', 'visibility' => 'public']);
    $grace->devotionals()->create(['title' => 'Walk by faith', 'slug' => 'walk', 'scripture' => '2 Corinthians 5:7', 'scripture_text' => 'For we walk by faith, not by sight.', 'body' => '<p>Trust Him today.</p>', 'publish_date' => today(), 'status' => 'published', 'visibility' => 'public']);

    $data = $this->getJson(route('api.v1.home'))->assertOk()->json('data');

    expect(collect($data['events'])->pluck('title')->all())->toBe(['Soon event', 'Later event'])
        ->and(collect($data['sermons'])->pluck('title')->all())->toBe(['New sermon', 'Old sermon'])
        ->and(collect($data['announcements'])->pluck('title')->all())->toBe(['Road closed', 'Plain notice'])
        ->and($data['announcements'][0])->toMatchArray(['urgent' => true])
        ->and($data['announcements'][1])->toMatchArray(['urgent' => false, 'body' => 'Bring a friend'])
        ->and(collect($data['campaigns'])->pluck('title')->all())->toBe(['Roof Fund'])
        ->and($data['campaigns'][0])->toMatchArray(['goal' => 1000000.0, 'raised' => 250000.0, 'percent' => 25.0])
        ->and($data['devotional'])->toMatchArray(['title' => 'Walk by faith', 'scripture' => '2 Corinthians 5:7', 'excerpt' => 'Trust Him today.'])
        ->and($data['devotional']['church']['slug'])->toBe('grace-lira');
});

test('followed churches show up as stories, with a live badge while they stream', function () {
    $member = signedInMember();
    $grace = homeChurch('Grace Lira', $member);
    homeChurch('Hope Gulu', $member);
    $grace->liveStreams()->create(['title' => 'Sunday Service', 'scheduled_at' => now(), 'source_type' => 'rtmp', 'status' => 'live', 'started_at' => now()]);

    $data = $this->getJson(route('api.v1.home'))->assertOk()->json('data');

    expect(collect($data['churches'])->pluck('is_live', 'slug')->all())->toBe(['grace-lira' => true, 'hope-gulu' => false])
        ->and($data['live'])->toHaveCount(1)
        ->and($data['live'][0])->toMatchArray(['title' => 'Sunday Service', 'status' => 'live']);
});

test('banner slides take turns between the member’s churches', function () {
    Storage::fake('public');
    $member = signedInMember();
    $grace = homeChurch('Grace Lira', $member);
    $hope = homeChurch('Hope Gulu', $member);
    foreach ([$grace, $hope] as $church) {
        foreach (range(1, 4) as $i) {
            $church->addMedia(UploadedFile::fake()->image("slide{$i}.jpg", 1600, 700))
                ->withCustomProperties(['title' => "{$church->name} {$i}"])->toMediaCollection('slides');
        }
    }

    $slides = $this->getJson(route('api.v1.home'))->assertOk()->json('data.slides');

    expect(collect($slides)->pluck('church.slug')->all())->toBe(['grace-lira', 'hope-gulu', 'grace-lira', 'hope-gulu', 'grace-lira', 'hope-gulu', 'grace-lira', 'hope-gulu'])
        ->and($slides[0])->toMatchArray(['kind' => 'slide', 'title' => 'Grace Lira 1'])
        ->and($slides[0]['image'])->toBeString()->not->toBeEmpty();
});

test('a church with too few slider photos still gets a banner from its cover photo', function () {
    Storage::fake('public');
    $member = signedInMember();
    $grace = homeChurch('Grace Lira', $member);
    $grace->update(['tagline' => 'A family of believers']);
    $grace->addMedia(UploadedFile::fake()->image('cover.jpg', 1600, 600))->toMediaCollection('cover');
    $grace->addMedia(UploadedFile::fake()->image('only.jpg', 1600, 700))->toMediaCollection('slides'); // below the minimum of 4

    $slides = $this->getJson(route('api.v1.home'))->assertOk()->json('data.slides');

    expect($slides)->toHaveCount(1)
        ->and($slides[0])->toMatchArray(['kind' => 'cover', 'title' => 'Grace Lira', 'subtitle' => 'A family of believers'])
        ->and($slides[0]['church']['slug'])->toBe('grace-lira');
});

test('feed cards carry what the app needs to draw them: church logo, scripture text and the urgent flag', function () {
    $member = signedInMember();
    $grace = homeChurch('Grace Lira', $member);
    $grace->devotionals()->create(['title' => 'Light for my path', 'slug' => 'light', 'scripture' => 'Psalm 119:105', 'scripture_text' => 'Your word is a lamp for my feet.', 'body' => '<p>Walk in it.</p>', 'publish_date' => today(), 'status' => 'published', 'visibility' => 'public']);
    $grace->announcements()->create(['title' => 'Road closed', 'body' => '<b>Use the back gate</b>', 'audience' => 'public', 'priority' => 'urgent', 'status' => 'published', 'published_at' => now()->subMinute()]);
    $grace->announcements()->create(['title' => 'Plain notice', 'body' => 'Hello', 'audience' => 'public', 'priority' => 'normal', 'status' => 'published', 'published_at' => now()->subMinutes(2)]);

    $items = collect($this->getJson(route('api.v1.feed'))->assertOk()->json('data'));

    $devotional = $items->firstWhere('type', 'devotional');
    $urgent = $items->first(fn ($i) => $i['type'] === 'announcement' && $i['item']['title'] === 'Road closed');
    $plain = $items->first(fn ($i) => $i['type'] === 'announcement' && $i['item']['title'] === 'Plain notice');

    expect($devotional['item'])->toMatchArray(['title' => 'Light for my path', 'scripture' => 'Psalm 119:105', 'scripture_text' => 'Your word is a lamp for my feet.'])
        ->and($devotional['church'])->toHaveKeys(['id', 'slug', 'name', 'logo_url'])
        ->and($urgent['item'])->toMatchArray(['urgent' => true, 'body' => 'Use the back gate'])
        ->and($plain['item']['urgent'])->toBeFalse();
});

test('the public church endpoint tells a signed-in app user whether they follow the church', function () {
    $member = signedInMember();
    $grace = homeChurch('Grace Lira', $member);
    $hope = homeChurch('Hope Gulu');

    $this->getJson(route('api.v1.churches.show', $grace->slug))->assertOk()->assertJsonPath('data.is_subscribed', true);
    $this->getJson(route('api.v1.churches.show', $hope->slug))->assertOk()->assertJsonPath('data.is_subscribed', false);
});
