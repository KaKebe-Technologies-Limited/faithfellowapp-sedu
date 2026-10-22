<?php

use App\Models\Church;
use App\Models\Reaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
| Announcements can be liked, loved and smiled at (one reaction per person), in the app and on the website. And a sermon recording bigger than the
| media library's old 10 MB limit is saved, as the form promises (150 MB for a church).
*/

function reactionChurch(): array
{
    $church = Church::factory()->create();
    $announcement = $church->announcements()->create(['title' => 'Cell groups meet Friday', 'body' => '<p>Bring a friend.</p>', 'audience' => 'members', 'priority' => 'normal', 'status' => 'published', 'published_at' => now()->subMinute()]);
    $follower = member();
    $church->subscriptions()->create(['user_id' => $follower->id, 'status' => 'active', 'subscribed_at' => now()]);

    return [$church, $announcement, $follower];
}

test('the announcement offers like, love and smile with how many chose each', function () {
    [, $announcement, $follower] = reactionChurch();

    Sanctum::actingAs($follower, ['member']);
    $this->getJson(route('api.v1.announcements.show', $announcement))->assertOk()
        ->assertJsonPath('data.reactions.types.0', ['key' => 'like', 'emoji' => '👍'])
        ->assertJsonPath('data.reactions.types.1', ['key' => 'love', 'emoji' => '❤️'])
        ->assertJsonPath('data.reactions.types.2', ['key' => 'smile', 'emoji' => '😊'])
        ->assertJsonPath('data.reactions.mine', null);
});

test('a person has one reaction: pressing another replaces it, pressing the same takes it back', function () {
    [, $announcement, $follower] = reactionChurch();
    $other = member();
    $announcement->church->subscriptions()->create(['user_id' => $other->id, 'status' => 'active', 'subscribed_at' => now()]);
    $url = route('api.v1.react', ['announcement', $announcement->id]);

    Sanctum::actingAs($follower, ['member']);
    $this->postJson($url, ['type' => 'like'])->assertOk()->assertJsonPath('counts.like', 1)->assertJsonPath('mine', 'like');
    $this->postJson($url, ['type' => 'love'])->assertOk()->assertJsonPath('counts.love', 1)->assertJsonMissingPath('counts.like')->assertJsonPath('mine', 'love');

    Sanctum::actingAs($other, ['member']);
    $this->postJson($url, ['type' => 'love'])->assertOk()->assertJsonPath('counts.love', 2);
    $this->getJson(route('api.v1.announcements.show', $announcement))->assertJsonPath('data.reactions.counts.love', 2)->assertJsonPath('data.reactions.mine', 'love');

    $this->postJson($url, ['type' => 'love'])->assertOk()->assertJsonPath('counts.love', 1)->assertJsonPath('mine', null);
    expect(Reaction::where('reactable_id', $announcement->id)->count())->toBe(1);
});

test('only people who may read the announcement can react to it, and only with a known reaction', function () {
    [, $announcement, $follower] = reactionChurch();
    $url = route('api.v1.react', ['announcement', $announcement->id]);

    Sanctum::actingAs(member(), ['member']);
    $this->postJson($url, ['type' => 'like'])->assertForbidden();

    Sanctum::actingAs($follower, ['member']);
    $this->postJson($url, ['type' => 'grumpy'])->assertUnprocessable();
    $this->postJson($url, [])->assertUnprocessable();
    $this->postJson(route('api.v1.react', ['nothing', 1]), ['type' => 'like'])->assertNotFound();
});

test('the website takes the same reactions on the announcement page', function () {
    [, $announcement, $follower] = reactionChurch();

    $this->actingAs($follower)->get(route('announcements.show', $announcement))->assertOk()->assertSee('👍')->assertSee('😊');
    $this->actingAs($follower)->postJson(route('reactions.toggle', ['announcement', $announcement->id]), ['type' => 'smile'])->assertOk()->assertJsonPath('mine', 'smile');
});

/** A sermon recording of the given size in MB that is sniffed as audio (ID3 + MPEG frames). */
function largeSermonAudio(int $mb): UploadedFile
{
    $frame = "\xFF\xFB\x90\x64".str_repeat("\x00", 413);

    return UploadedFile::fake()->createWithContent('long-sermon.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat($frame, (int) ceil($mb * 1048576 / strlen($frame))));
}

test('a sermon recording of 24 MB is saved: the media library no longer stops anything over 10 MB', function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);
    $admin = member();
    $church = Church::factory()->create();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);

    expect(config('media-library.max_file_size'))->toBeGreaterThanOrEqual(400 * 1048576);

    $this->actingAs($admin)->postJson(route('church-admin.sermons.store', $church), ['title' => 'Focus on yourself', 'language' => 'en', 'status' => 'draft', 'visibility' => 'public', 'audio' => largeSermonAudio(24)])
        ->assertOk()->assertJsonStructure(['redirect', 'message']);

    $sermon = $church->sermons()->where('title', 'Focus on yourself')->first();
    expect($sermon->getFirstMedia('audio'))->not->toBeNull()->and($sermon->getFirstMedia('audio')->size)->toBeGreaterThan(24 * 1048576);
});
