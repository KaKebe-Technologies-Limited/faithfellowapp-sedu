<?php

use App\Models\Church;
use App\Models\ContentReport;
use App\Models\Podcast;
use App\Models\PodcastComment;
use App\Models\PodcastEpisode;
use App\Models\RadioStation;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
| Listeners comment under a podcast episode or under a podcast channel of the radio (the Podcast radio, a church's channel).
*/

function commentEpisode(?User $maker = null, string $status = 'approved'): PodcastEpisode
{
    $podcast = Podcast::create([
        'user_id' => ($maker ?? member())->id, 'name' => 'Morning Manna', 'host_name' => 'Pastor Ruth', 'summary' => 'A short word from the Bible each day.',
        'category' => 'devotional', 'language' => 'en', 'status' => $status, 'approved_at' => $status === 'approved' ? now() : null,
    ]);

    return $podcast->episodes()->create(['title' => 'Walking by faith', 'audio_url' => 'https://audio.example.com/walking.mp3', 'duration_seconds' => 1450, 'status' => 'published', 'published_at' => now()->subHour()]);
}

test('a listener comments under an episode and everyone who can hear it reads it, newest first', function () {
    $episode = commentEpisode();
    $ann = member(['name' => 'Ann Akello']);
    $bob = member(['name' => 'Bob Okello']);

    Sanctum::actingAs($ann, ['member']);
    $this->postJson(route('api.v1.podcast-comments.episode.store', $episode), ['body' => 'This word came at the right time.'])->assertCreated()
        ->assertJsonPath('data.mine', true)->assertJsonPath('data.can_delete', true)->assertJsonPath('data.author.name', 'Ann Akello');

    Sanctum::actingAs($bob, ['member']);
    $this->postJson(route('api.v1.podcast-comments.episode.store', $episode), ['body' => 'Amen, thank you for sharing.'])->assertCreated();

    $list = $this->getJson(route('api.v1.podcast-comments.episode', $episode))->assertOk()->json();
    expect(collect($list['data'])->pluck('body')->all())->toBe(['Amen, thank you for sharing.', 'This word came at the right time.'])
        ->and($list['data'][0]['mine'])->toBeTrue()->and($list['data'][1]['mine'])->toBeFalse()->and($list['data'][1]['can_delete'])->toBeFalse()
        ->and($list['meta']['total'])->toBe(2);
});

test('a comment needs real words, no contact details, no tags, and the same words twice are one comment', function () {
    $episode = commentEpisode();
    Sanctum::actingAs(member(), ['member']);
    $url = route('api.v1.podcast-comments.episode.store', $episode);

    $this->postJson($url, ['body' => ''])->assertUnprocessable();
    $this->postJson($url, ['body' => 'x'])->assertUnprocessable();
    $this->postJson($url, ['body' => str_repeat('a', 601)])->assertUnprocessable();
    $this->postJson($url, ['body' => 'Call me on 0772 123 456 for prayer'])->assertUnprocessable();
    $this->postJson($url, ['body' => '<b>Blessed</b> <script>alert(1)</script>morning'])->assertCreated()->assertJsonPath('data.body', 'Blessed alert(1)morning');
    $this->postJson($url, ['body' => 'Blessed morning again'])->assertCreated();
    $this->postJson($url, ['body' => 'Blessed morning again'])->assertUnprocessable();
    expect(PodcastComment::count())->toBe(2);
});

test('an episode that is not out, or whose podcast is not approved, has no comments', function () {
    Sanctum::actingAs(member(), ['member']);
    $pending = commentEpisode(null, 'pending');
    $draft = commentEpisode();
    $draft->update(['status' => 'draft']);

    $this->getJson(route('api.v1.podcast-comments.episode', $pending))->assertNotFound();
    $this->postJson(route('api.v1.podcast-comments.episode.store', $pending), ['body' => 'Hello there'])->assertNotFound();
    $this->getJson(route('api.v1.podcast-comments.episode', $draft))->assertNotFound();
});

test('the author, the podcast maker and the super admin can remove a comment, nobody else', function () {
    $maker = member();
    $episode = commentEpisode($maker);
    $ann = member();
    $other = member();
    Sanctum::actingAs($ann, ['member']);
    $mine = $this->postJson(route('api.v1.podcast-comments.episode.store', $episode), ['body' => 'My thoughts on this episode'])->json('data.id');

    Sanctum::actingAs($other, ['member']);
    $this->deleteJson(route('api.v1.podcast-comments.destroy', $mine))->assertForbidden();

    Sanctum::actingAs($maker, ['member']);
    expect($this->getJson(route('api.v1.podcast-comments.episode', $episode))->json('data.0.can_delete'))->toBeTrue();
    $this->deleteJson(route('api.v1.podcast-comments.destroy', $mine))->assertOk();
    expect(PodcastComment::count())->toBe(0);

    Sanctum::actingAs($ann, ['member']);
    $again = $this->postJson(route('api.v1.podcast-comments.episode.store', $episode), ['body' => 'Another thought here'])->json('data.id');
    $this->deleteJson(route('api.v1.podcast-comments.destroy', $again))->assertOk();   // the author

    $third = PodcastComment::create(['commentable_type' => $episode->getMorphClass(), 'commentable_id' => $episode->id, 'user_id' => $ann->id, 'body' => 'And a third']);
    Sanctum::actingAs(superAdmin(), ['member']);
    $this->deleteJson(route('api.v1.podcast-comments.destroy', $third->id))->assertOk();
});

test('a reported comment is held back for a moderator, who can hide it or put it back', function () {
    $episode = commentEpisode();
    $ann = member();
    $bob = member();
    $comment = PodcastComment::create(['commentable_type' => $episode->getMorphClass(), 'commentable_id' => $episode->id, 'user_id' => $ann->id, 'body' => 'Something unkind']);

    Sanctum::actingAs($ann, ['member']);
    $this->postJson(route('api.v1.podcast-comments.report', $comment), ['reason' => 'abuse'])->assertUnprocessable();   // not your own

    Sanctum::actingAs($bob, ['member']);
    $this->postJson(route('api.v1.podcast-comments.report', $comment), ['reason' => 'nonsense'])->assertUnprocessable();
    $this->postJson(route('api.v1.podcast-comments.report', $comment), ['reason' => 'abuse', 'details' => 'It was aimed at someone'])->assertOk();
    expect($comment->fresh()->status)->toBe('flagged')->and($this->getJson(route('api.v1.podcast-comments.episode', $episode))->json('data'))->toBe([]);

    $report = ContentReport::sole();
    expect($report->church_id)->toBeNull()->and($report->reportable_type)->toBe(PodcastComment::class);
    $admin = superAdmin();
    $this->actingAs($admin)->get(route('super-admin.moderation.index'))->assertOk()->assertSee('Something unkind')->assertSee('Podcast Comment');

    $this->actingAs($admin)->patch(route('super-admin.moderation.update', $report), ['action' => 'dismiss'])->assertRedirect();
    expect($comment->fresh()->status)->toBe('visible');

    $second = member();
    Sanctum::actingAs($second, ['member']);
    $this->postJson(route('api.v1.podcast-comments.report', $comment), ['reason' => 'spam'])->assertOk();
    $this->actingAs($admin)->patch(route('super-admin.moderation.update', ContentReport::where('user_id', $second->id)->sole()), ['action' => 'hide'])->assertRedirect();
    expect($comment->fresh()->status)->toBe('hidden');
});

test('the Podcast radio has a comment wall for everyone, and a church channel only for the people of that church', function () {
    $church = Church::factory()->create();
    $follower = member();
    $church->subscriptions()->create(['user_id' => $follower->id, 'status' => 'active', 'subscribed_at' => now()]);
    $staff = member();
    $church->staffMembers()->create(['user_id' => $staff->id, 'is_admin' => true, 'status' => 'active']);
    $stranger = member();
    RadioStation::podcast();
    RadioStation::forChurch($church);
    $slug = 'church-'.$church->id;

    Sanctum::actingAs($stranger, ['member']);
    $this->postJson(route('api.v1.radio.station.comments.store', 'podcast'), ['body' => 'Lovely teaching tonight'])->assertCreated();
    $this->getJson(route('api.v1.radio.station.comments', 'podcast'))->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson(route('api.v1.radio.station.comments', $slug))->assertNotFound();
    $this->postJson(route('api.v1.radio.station.comments.store', $slug), ['body' => 'Let me in'])->assertNotFound();
    $this->getJson(route('api.v1.radio.station.comments', 'gospel'))->assertNotFound();   // the music radio has no comment wall

    Sanctum::actingAs($follower, ['member']);
    $id = $this->postJson(route('api.v1.radio.station.comments.store', $slug), ['body' => 'Thank you pastor for this'])->assertCreated()->json('data.id');

    Sanctum::actingAs($stranger, ['member']);
    $this->deleteJson(route('api.v1.podcast-comments.destroy', $id))->assertForbidden();
    Sanctum::actingAs($staff, ['member']);
    expect($this->getJson(route('api.v1.radio.station.comments', $slug))->json('data.0.can_delete'))->toBeTrue();
    $this->deleteJson(route('api.v1.podcast-comments.destroy', $id))->assertOk();
});

test('long comment lists come in pages', function () {
    $episode = commentEpisode();
    $ann = member();
    foreach (range(1, 17) as $i) {
        PodcastComment::create(['commentable_type' => $episode->getMorphClass(), 'commentable_id' => $episode->id, 'user_id' => $ann->id, 'body' => "Comment number {$i}"]);
    }
    Sanctum::actingAs(member(), ['member']);

    $first = $this->getJson(route('api.v1.podcast-comments.episode', $episode))->assertOk()->json();
    expect($first['data'])->toHaveCount(15)->and($first['meta'])->toMatchArray(['total' => 17, 'last_page' => 2])->and($first['data'][0]['body'])->toBe('Comment number 17');
    expect($this->getJson(route('api.v1.podcast-comments.episode', ['episode' => $episode, 'page' => 2]))->json('data'))->toHaveCount(2);
});
