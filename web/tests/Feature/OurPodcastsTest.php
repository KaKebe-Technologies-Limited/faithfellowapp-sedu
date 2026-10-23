<?php

use App\Models\Church;
use App\Models\RadioStation;
use App\Models\RadioTrack;
use Laravel\Sanctum\Sanctum;

/*
| "Our Podcasts" at the top of the Podcasts screen: the episodes the churches a member follows have put out, newest first, to play on demand, one block for
| each church. Only when none of their churches has any does the platform's own podcast collection stand in.
*/

function episode(RadioStation $station, string $title, int $position = 1, array $with = []): RadioTrack
{
    return RadioTrack::create($with + ['station_id' => $station->id, 'title' => $title, 'artist' => 'Pastor Okello', 'file_path' => 'radio/'.str($title)->slug().'.mp3', 'mime' => 'audio/mpeg', 'size' => 4000, 'duration_seconds' => 300, 'position' => $position, 'is_active' => true]);
}

function ours(): array
{
    return test()->getJson(route('api.v1.podcasts.overview'))->assertOk()->json('data.ours');
}

test('the episodes of the churches a member follows are listed newest first, with the church that made them', function () {
    $church = Church::factory()->create(['name' => 'Grace Church']);
    $station = RadioStation::forChurch($church);
    episode($station, 'First sermon', 1);
    episode($station, 'Second sermon', 2);
    episode($station, 'Third sermon', 3);
    $follower = member();
    $church->subscriptions()->create(['user_id' => $follower->id, 'status' => 'active', 'subscribed_at' => now()]);

    Sanctum::actingAs($follower, ['member']);
    $block = ours()[0];
    expect(ours())->toHaveCount(1)
        ->and($block)->toMatchArray(['slug' => 'church-'.$church->id, 'name' => 'Grace Church', 'total' => 3])
        ->and($block['church'])->toMatchArray(['id' => $church->id, 'slug' => $church->slug, 'name' => 'Grace Church'])
        ->and(collect($block['episodes'])->pluck('title')->all())->toBe(['Third sermon', 'Second sermon', 'First sermon'])
        ->and($block['episodes'][0])->toHaveKeys(['id', 'title', 'artist', 'duration', 'cover_url', 'stream_url'])
        ->and($block['episodes'][0]['stream_url'])->toContain('/radio/');
});

test('a church the member does not follow is not in their list, and a church with no episodes is left out', function () {
    $followed = Church::factory()->create(['name' => 'Followed Church']);
    $empty = Church::factory()->create(['name' => 'Empty Church']);
    $stranger = Church::factory()->create(['name' => 'Stranger Church']);
    episode(RadioStation::forChurch($followed), 'Ours');
    RadioStation::forChurch($empty);
    episode(RadioStation::forChurch($stranger), 'Not ours');
    $user = member();
    foreach ([$followed, $empty] as $church) {
        $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);
    }

    Sanctum::actingAs($user, ['member']);
    expect(collect(ours())->pluck('name')->all())->toBe(['Followed Church']);
});

test('a church that switched its channel off or was blocked is not listed, nor are episodes taken out of the loop', function () {
    $church = Church::factory()->create();
    $station = RadioStation::forChurch($church);
    episode($station, 'On', 1);
    episode($station, 'Taken out', 2, ['is_active' => false]);
    $user = member();
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);

    Sanctum::actingAs($user, ['member']);
    expect(ours()[0]['total'])->toBe(1)->and(collect(ours()[0]['episodes'])->pluck('title')->all())->toBe(['On']);

    $station->update(['is_enabled' => false]);
    expect(ours())->toBe([]);
    $station->update(['is_enabled' => true, 'is_blocked' => true]);
    expect(ours())->toBe([]);
});

test('without any church episodes the platform\'s own podcast collection stands in, and never alongside the church\'s', function () {
    episode(RadioStation::podcast(), 'From FaithFellows');
    $user = member();

    Sanctum::actingAs($user, ['member']);
    $block = ours()[0];
    expect($block['church'])->toBeNull()->and($block['slug'])->toBe(RadioStation::podcast()->slug)->and($block['episodes'][0]['title'])->toBe('From FaithFellows');

    $church = Church::factory()->create(['name' => 'Grace Church']);
    episode(RadioStation::forChurch($church), 'Our church sermon');
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);
    expect(collect(ours())->pluck('name')->all())->toBe(['Grace Church']);
});

test('a block carries the newest twenty episodes and says how many there are in all', function () {
    $church = Church::factory()->create();
    $station = RadioStation::forChurch($church);
    foreach (range(1, 23) as $i) {
        episode($station, "Episode {$i}", $i);
    }
    $user = member();
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);

    Sanctum::actingAs($user, ['member']);
    $block = ours()[0];
    expect($block['total'])->toBe(23)->and($block['episodes'])->toHaveCount(20)->and($block['episodes'][0]['title'])->toBe('Episode 23');
});

test('nothing is listed when there is nothing to play, and the rest of the overview still loads', function () {
    Sanctum::actingAs(member(), ['member']);
    $this->getJson(route('api.v1.podcasts.overview'))->assertOk()->assertJsonPath('data.ours', [])->assertJsonStructure(['data' => ['categories', 'sections', 'following', 'new_episodes', 'podcasts']]);
});
