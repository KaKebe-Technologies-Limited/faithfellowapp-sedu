<?php

use App\Models\Church;
use App\Models\RadioStation;
use App\Models\RadioTrack;
use Laravel\Sanctum\Sanctum;

/*
| A church's page in the app shows everything about the church in one place: its sermons (with audio), events, giving campaigns, updates, notices,
| its own radio channel and the songs in it, photos and links. What a church keeps for its members is only for the people who follow it.
*/

/** A church with public and members-only content of every kind. */
function pageChurch(): Church
{
    $church = Church::factory()->create(['name' => 'Epifania Church']);
    $church->sermons()->create(['title' => 'Public sermon', 'language' => 'en', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDay(), 'audio_url' => 'https://audio.example.com/public.mp3', 'preacher' => 'Pastor Ruth', 'duration_seconds' => 1800]);
    $church->sermons()->create(['title' => 'Members sermon', 'language' => 'en', 'status' => 'published', 'visibility' => 'members', 'published_at' => now()->subHours(3)]);
    $church->events()->create(['title' => 'Youth night', 'starts_at' => now()->addDays(3), 'status' => 'published', 'visibility' => 'public']);
    $church->events()->create(['title' => 'Leaders retreat', 'starts_at' => now()->addDays(5), 'status' => 'published', 'visibility' => 'members']);
    $church->posts()->create(['user_id' => member()->id, 'title' => 'News', 'body' => '<p>The new choir robes arrived.</p>', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subHour()]);
    $church->announcements()->create(['title' => 'Public notice', 'body' => '<p>Sunday service moves to 9.</p>', 'audience' => 'public', 'priority' => 'urgent', 'status' => 'published', 'published_at' => now()->subMinutes(5)]);
    $church->announcements()->create(['title' => 'Members notice', 'body' => '<p>Cell groups meet Friday.</p>', 'audience' => 'members', 'priority' => 'normal', 'status' => 'published', 'published_at' => now()->subMinutes(6)]);

    $station = RadioStation::forChurch($church);
    RadioTrack::create(['station_id' => $station->id, 'title' => 'Faith at Work', 'artist' => 'Rev. Okello', 'file_path' => 'radio/a.mp3', 'mime' => 'audio/mpeg', 'size' => 4000, 'duration_seconds' => 240, 'position' => 1, 'is_active' => true]);

    return $church;
}

test('anyone sees what a church makes public, and nothing it keeps for its members', function () {
    $church = pageChurch();

    $page = $this->getJson(route('api.v1.churches.page', $church))->assertOk()->json('data');

    expect(collect($page['sermons'])->pluck('title')->all())->toBe(['Public sermon'])
        ->and($page['sermons'][0]['audio_url'])->not->toBeNull()
        ->and(collect($page['events'])->pluck('title')->all())->toBe(['Youth night'])
        ->and($page['updates'][0]['body'])->toBe('The new choir robes arrived.')
        ->and(collect($page['announcements'])->pluck('title')->all())->toBe(['Public notice'])
        ->and($page['announcements'][0]['urgent'])->toBeTrue()
        ->and($page['stats'])->toMatchArray(['followers' => 0, 'sermons' => 1, 'events' => 1])
        ->and($page['live'])->toBeNull();

    // The church has a channel, but its songs are for the people who follow the church.
    expect($page['channel'])->toMatchArray(['slug' => 'church-'.$church->id, 'songs' => 1, 'can_listen' => false, 'tracks' => []]);
});

test('a follower also gets the members-only content and the songs of the channel to play', function () {
    $church = pageChurch();
    $user = member();
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);

    Sanctum::actingAs($user, ['member']);
    $page = $this->getJson(route('api.v1.churches.page', $church))->assertOk()->json('data');

    expect(collect($page['sermons'])->pluck('title')->all())->toEqualCanonicalizing(['Public sermon', 'Members sermon'])
        ->and(collect($page['events'])->pluck('title')->all())->toEqualCanonicalizing(['Youth night', 'Leaders retreat'])
        ->and(collect($page['announcements'])->pluck('title')->all())->toEqualCanonicalizing(['Public notice', 'Members notice'])
        ->and($page['stats'])->toMatchArray(['followers' => 1, 'sermons' => 2, 'events' => 2])
        ->and($page['channel']['can_listen'])->toBeTrue()
        ->and($page['channel']['tracks'][0])->toMatchArray(['title' => 'Faith at Work', 'artist' => 'Rev. Okello', 'duration' => 240])
        ->and($page['channel']['tracks'][0]['stream_url'])->toContain('/radio/stream/');
});

test('a church with no channel, an empty one or one the platform blocked shows no channel, and a church that is not active has no page', function () {
    $bare = Church::factory()->create();
    $this->getJson(route('api.v1.churches.page', $bare))->assertOk()->assertJsonPath('data.channel', null)->assertJsonPath('data.sermons', []);

    $church = pageChurch();
    RadioStation::forChurch($church)->update(['is_blocked' => true]);
    $this->getJson(route('api.v1.churches.page', $church))->assertOk()->assertJsonPath('data.channel', null);

    $this->getJson(route('api.v1.churches.page', Church::factory()->create(['status' => 'suspended'])))->assertNotFound();
});
