<?php

use App\Models\Podcast;
use App\Models\PodcastEpisode;
use App\Models\User;
use App\Notifications\Podcasts\PodcastReviewed;
use App\Services\PodcastService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
| Podcasts: members make faith-based podcasts on the website and add episodes as links to audio on a host site; the
| super admin approves a podcast before it shows in the app, where listeners search, follow and play.
*/

beforeEach(function () {
    Cache::forget('platform_settings');
    $GLOBALS['podcast_http'] = [];
    // Every host answers like an audio file, unless a test says otherwise for one host.
    Http::fake(fn ($request) => $GLOBALS['podcast_http'][$request->toPsrRequest()->getUri()->getHost()] ?? Http::response('', 200, ['Content-Type' => 'audio/mpeg']));
});

function podcastData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Morning Manna', 'host_name' => 'Pastor Ruth', 'summary' => 'A short word of encouragement from the Bible every morning.',
        'category' => 'devotional', 'language' => 'en', 'faith_based' => 1,
    ], $overrides);
}

function aPodcast(?User $owner = null, string $status = 'approved', string $name = 'Morning Manna'): Podcast
{
    return Podcast::create([
        'user_id' => ($owner ?? member())->id, 'name' => $name, 'host_name' => 'Pastor Ruth', 'summary' => 'A short word from the Bible each day.',
        'category' => 'devotional', 'language' => 'en', 'status' => $status, 'approved_at' => $status === 'approved' ? now() : null,
    ]);
}

function anEpisode(Podcast $podcast, array $overrides = []): PodcastEpisode
{
    return $podcast->episodes()->create(array_merge(['title' => 'Walking by faith', 'audio_url' => 'https://audio.example.com/walking.mp3', 'duration_seconds' => 1450, 'status' => 'published', 'published_at' => now()->subDay()], $overrides));
}

// ── The studio on the website ───────────────────────────────────────────────────────────────────────────

test('a member starts a podcast, which waits for review', function () {
    Storage::fake('public');
    $ruth = member(['name' => 'Ruth']);

    $this->actingAs($ruth)->get(route('podcasts.index'))->assertOk()->assertSee('You have no podcast yet');
    $this->actingAs($ruth)->get(route('podcasts.create'))->assertOk()->assertSee('Presented by');

    $this->actingAs($ruth)->post(route('podcasts.store'), podcastData(['cover' => UploadedFile::fake()->image('cover.jpg', 600, 600)]))->assertRedirect();
    $podcast = Podcast::sole();
    expect($podcast->status)->toBe('pending')->and($podcast->user_id)->toBe($ruth->id)->and($podcast->slug)->toBe('morning-manna')
        ->and($podcast->getFirstMedia('cover'))->not->toBeNull();

    $this->actingAs($ruth)->get(route('podcasts.show', $podcast))->assertOk()->assertSee('waiting for review');
    $this->actingAs($ruth)->get(route('podcasts.index'))->assertOk()->assertSee('Morning Manna');
});

test('the podcast needs the faith-based promise and no contact details in its text', function () {
    $ruth = member();

    $this->actingAs($ruth)->post(route('podcasts.store'), podcastData(['faith_based' => 0]))->assertSessionHasErrors('faith_based');
    $this->actingAs($ruth)->post(route('podcasts.store'), podcastData(['summary' => 'Call me on 0777676206 for prayer and encouragement every day.']))->assertSessionHasErrors('summary');
    $this->actingAs($ruth)->post(route('podcasts.store'), podcastData(['summary' => 'Too short']))->assertSessionHasErrors('summary');
    $this->actingAs($ruth)->post(route('podcasts.store'), podcastData(['category' => 'cooking']))->assertSessionHasErrors('category');
    expect(Podcast::count())->toBe(0);

    // The slug stays unique when two podcasts share a name.
    $this->actingAs($ruth)->post(route('podcasts.store'), podcastData())->assertRedirect();
    $this->actingAs($ruth)->post(route('podcasts.store'), podcastData())->assertRedirect();
    expect(Podcast::orderBy('id')->pluck('slug')->all())->toBe(['morning-manna', 'morning-manna-2']);
});

test('only the podcaster can see or change their podcast', function () {
    $ruth = member();
    $podcast = aPodcast($ruth);
    $other = member();

    $this->actingAs($other)->get(route('podcasts.show', $podcast))->assertNotFound();
    $this->actingAs($other)->put(route('podcasts.update', $podcast), podcastData())->assertNotFound();
    $this->actingAs($other)->post(route('podcasts.episodes.store', $podcast), ['title' => 'x', 'audio_url' => 'https://a.example.com/x.mp3'])->assertNotFound();
    $this->actingAs($other)->delete(route('podcasts.destroy', $podcast))->assertNotFound();
});

test('a podcast that was not approved goes back for review when it is changed', function () {
    $ruth = member();
    $podcast = aPodcast($ruth, 'rejected');
    $podcast->update(['review_note' => 'Not about faith']);

    $this->actingAs($ruth)->get(route('podcasts.show', $podcast))->assertOk()->assertSee('Not about faith');
    $this->actingAs($ruth)->put(route('podcasts.update', $podcast), podcastData(['name' => 'Morning Manna Live']))->assertRedirect();

    expect($podcast->fresh()->status)->toBe('pending')->and($podcast->fresh()->name)->toBe('Morning Manna Live');
});

// ── Episodes ────────────────────────────────────────────────────────────────────────────────────────────

test('an episode is added with a link to its audio, and the length can be typed as minutes and seconds', function () {
    $ruth = member();
    $podcast = aPodcast($ruth);

    $this->actingAs($ruth)->get(route('podcasts.episodes.create', $podcast))->assertOk()->assertSee('Link to the audio');
    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'Walking by faith', 'audio_url' => 'https://audio.example.com/walking.mp3', 'duration' => '24:10'])->assertRedirect(route('podcasts.show', $podcast));

    $episode = $podcast->episodes()->sole();
    expect($episode->duration_seconds)->toBe(1450)->and($episode->status)->toBe('published')->and($episode->published_at)->not->toBeNull()
        ->and($podcast->fresh()->episodes_count)->toBe(1);

    expect(PodcastService::parseDuration('24:10'))->toBe(1450)->and(PodcastService::parseDuration('24'))->toBe(1440)->and(PodcastService::parseDuration('1:02:05'))->toBe(3725)->and(PodcastService::parseDuration(''))->toBeNull()->and(PodcastService::parseDuration('abc'))->toBeNull();
    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'Bad', 'audio_url' => 'https://audio.example.com/b.mp3', 'duration' => 'soon'])->assertSessionHasErrors('duration');
});

test('drafts and scheduled episodes are kept out of the count until they are out', function () {
    $ruth = member();
    $podcast = aPodcast($ruth);

    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'Draft', 'audio_url' => 'https://audio.example.com/d.mp3', 'draft' => 1]);
    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'Next week', 'audio_url' => 'https://audio.example.com/n.mp3', 'published_at' => now()->addWeek()->format('Y-m-d\TH:i')]);
    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'Today', 'audio_url' => 'https://audio.example.com/t.mp3']);

    expect($podcast->episodes()->count())->toBe(3)->and($podcast->fresh()->episodes_count)->toBe(1);
});

test('a link that opens a web page, or is missing, is refused with a reason', function () {
    $ruth = member();
    $podcast = aPodcast($ruth);

    $GLOBALS['podcast_http']['page.example.com'] = Http::response('<html>', 200, ['Content-Type' => 'text/html; charset=utf-8']);
    $GLOBALS['podcast_http']['gone.example.com'] = Http::response('', 404);
    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'A', 'audio_url' => 'https://page.example.com/episode-1'])->assertSessionHasErrors(['audio_url' => 'That link opens a web page, not an audio file. Use the direct link to the audio (it usually ends in .mp3 or .m4a).']);
    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'B', 'audio_url' => 'https://gone.example.com/x.mp3'])->assertSessionHasErrors('audio_url');
    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'C', 'audio_url' => 'http://audio.example.com/x.mp3'])->assertSessionHasErrors('audio_url');
    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'D', 'audio_url' => 'https://localhost/x.mp3'])->assertSessionHasErrors('audio_url');
    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'E', 'audio_url' => 'https://127.0.0.1/x.mp3'])->assertSessionHasErrors('audio_url');
    expect($podcast->episodes()->count())->toBe(0);
});

test('when the super admin names host sites, audio must come from one of them', function () {
    $ruth = member();
    $podcast = aPodcast($ruth);
    app(PodcastService::class)->saveHosts('podbean.com, https://www.buzzsprout.com/');
    expect(app(PodcastService::class)->allowedHosts())->toBe(['podbean.com', 'www.buzzsprout.com']);

    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'Elsewhere', 'audio_url' => 'https://random.example.org/a.mp3'])->assertSessionHasErrors('audio_url');
    $this->actingAs($ruth)->post(route('podcasts.episodes.store', $podcast), ['title' => 'On Podbean', 'audio_url' => 'https://mcdn.podbean.com/mf/web/abc/a.mp3'])->assertRedirect();
    expect($podcast->episodes()->sole()->title)->toBe('On Podbean');
});

test('episodes can be edited and deleted by their podcaster', function () {
    $ruth = member();
    $podcast = aPodcast($ruth);
    $episode = anEpisode($podcast);

    $this->actingAs($ruth)->get(route('podcasts.episodes.edit', [$podcast, $episode]))->assertOk()->assertSee('24:10');
    $this->actingAs($ruth)->put(route('podcasts.episodes.update', [$podcast, $episode]), ['title' => 'Walking by faith, part 2', 'audio_url' => 'https://audio.example.com/walking.mp3', 'duration' => '25:00'])->assertRedirect();
    expect($episode->fresh()->title)->toBe('Walking by faith, part 2')->and($episode->fresh()->duration_seconds)->toBe(1500);

    $this->actingAs($ruth)->delete(route('podcasts.episodes.destroy', [$podcast, $episode]))->assertRedirect();
    expect($podcast->fresh()->episodes_count)->toBe(0);
});

// ── The super admin ─────────────────────────────────────────────────────────────────────────────────────

test('the super admin reviews: approve, say why not, or hide, and the podcaster is told', function () {
    Notification::fake();
    $admin = superAdmin();
    $ruth = member();
    $podcast = aPodcast($ruth, 'pending');
    anEpisode($podcast);

    $this->actingAs(member())->get(route('super-admin.podcasts.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('super-admin.podcasts.index'))->assertOk()->assertSee('Morning Manna');
    $this->actingAs($admin)->get(route('super-admin.podcasts.show', $podcast))->assertOk()->assertSee('Walking by faith')->assertSee('Approve');

    $this->actingAs($admin)->post(route('super-admin.podcasts.reject', $podcast), [])->assertSessionHasErrors('reason');
    $this->actingAs($admin)->post(route('super-admin.podcasts.reject', $podcast), ['reason' => 'The audio links do not open.'])->assertRedirect();
    expect($podcast->fresh()->status)->toBe('rejected')->and($podcast->fresh()->review_note)->toBe('The audio links do not open.');
    Notification::assertSentTo($ruth, PodcastReviewed::class, fn ($n) => $n->outcome === 'rejected');

    $this->actingAs($admin)->post(route('super-admin.podcasts.approve', $podcast))->assertRedirect();
    expect($podcast->fresh()->status)->toBe('approved')->and($podcast->fresh()->review_note)->toBeNull()->and($podcast->fresh()->approved_at)->not->toBeNull();
    Notification::assertSentTo($ruth, PodcastReviewed::class, fn ($n) => $n->outcome === 'approved');

    $this->actingAs($admin)->post(route('super-admin.podcasts.hide', $podcast), ['reason' => 'Reported'])->assertRedirect();
    expect($podcast->fresh()->status)->toBe('hidden');

    $this->actingAs($admin)->put(route('super-admin.podcasts.hosts'), ['hosts' => 'podbean.com,\nbuzzsprout.com'])->assertRedirect();
    expect(app(PodcastService::class)->allowedHosts())->toContain('podbean.com');
});

// ── The app ─────────────────────────────────────────────────────────────────────────────────────────────

test('listeners see only approved podcasts, with their episodes that are out', function () {
    $listener = member();
    $live = aPodcast(null, 'approved', 'Live Podcast');
    $waiting = aPodcast(null, 'pending', 'Waiting Podcast');
    $hidden = aPodcast(null, 'hidden', 'Hidden Podcast');
    anEpisode($live, ['title' => 'Out now']);
    anEpisode($live, ['title' => 'Coming later', 'published_at' => now()->addWeek()]);
    anEpisode($live, ['title' => 'A draft', 'status' => 'draft']);
    anEpisode($waiting);

    $this->getJson(route('api.v1.podcasts.index'))->assertUnauthorized();
    Sanctum::actingAs($listener, ['member']);

    $list = $this->getJson(route('api.v1.podcasts.index'))->assertOk()->json('data');
    expect(collect($list)->pluck('name')->all())->toBe(['Live Podcast'])->and($list[0])->toHaveKeys(['id', 'name', 'host', 'summary', 'category', 'category_label', 'cover_url', 'episodes', 'following']);

    $detail = $this->getJson(route('api.v1.podcasts.show', $live))->assertOk()->json('data');
    expect(collect($detail['episodes'])->pluck('title')->all())->toBe(['Out now'])->and($detail['episodes'][0])->toHaveKeys(['id', 'title', 'audio_url', 'duration', 'published_at', 'podcast'])
        ->and($detail['episodes'][0]['audio_url'])->toBe('https://audio.example.com/walking.mp3');
    $this->getJson(route('api.v1.podcasts.show', $waiting))->assertNotFound();
    $this->getJson(route('api.v1.podcasts.show', $hidden))->assertNotFound();
    $this->getJson(route('api.v1.podcasts.episodes', $waiting))->assertNotFound();
});

test('listeners search by name, host or summary and browse by category', function () {
    Sanctum::actingAs(member(), ['member']);
    $a = aPodcast(null, 'approved', 'Bible in a Year');
    $a->update(['category' => 'teaching']);
    $b = aPodcast(null, 'approved', 'Youth Talk Lira');
    $b->update(['category' => 'youth', 'host_name' => 'Brother Moses', 'summary' => 'Real talk for young believers.']);
    anEpisode($a);
    anEpisode($b);

    expect(collect($this->getJson(route('api.v1.podcasts.index', ['q' => 'youth']))->json('data'))->pluck('name')->all())->toBe(['Youth Talk Lira'])
        ->and(collect($this->getJson(route('api.v1.podcasts.index', ['q' => 'Moses']))->json('data'))->pluck('name')->all())->toBe(['Youth Talk Lira'])
        ->and(collect($this->getJson(route('api.v1.podcasts.index', ['category' => 'teaching']))->json('data'))->pluck('name')->all())->toBe(['Bible in a Year'])
        ->and($this->getJson(route('api.v1.podcasts.index', ['category' => 'cooking']))->assertUnprocessable());

    $overview = $this->getJson(route('api.v1.podcasts.overview'))->assertOk()->json('data');
    expect(collect($overview['categories'])->pluck('key')->sort()->values()->all())->toBe(['teaching', 'youth'])
        ->and($overview['new_episodes'])->toHaveCount(2)->and($overview['podcasts'])->toHaveCount(2)->and($overview['following'])->toBe([]);
});

test('following a podcast puts it in your library and counts the follower', function () {
    $listener = member();
    $podcast = aPodcast(null, 'approved');
    anEpisode($podcast);
    Sanctum::actingAs($listener, ['member']);

    $this->postJson(route('api.v1.podcasts.follow', $podcast))->assertOk()->assertJson(['following' => true, 'followers' => 1]);
    $this->postJson(route('api.v1.podcasts.follow', $podcast))->assertOk()->assertJson(['followers' => 1]);
    expect($this->getJson(route('api.v1.podcasts.show', $podcast))->json('data.following'))->toBeTrue()
        ->and(collect($this->getJson(route('api.v1.podcasts.overview'))->json('data.following'))->pluck('id')->all())->toBe([$podcast->id])
        ->and(collect($this->getJson(route('api.v1.podcasts.index', ['following' => 1]))->json('data'))->pluck('id')->all())->toBe([$podcast->id]);

    $this->deleteJson(route('api.v1.podcasts.unfollow', $podcast))->assertOk()->assertJson(['following' => false, 'followers' => 0]);
    expect($this->getJson(route('api.v1.podcasts.overview'))->json('data.following'))->toBe([]);
});

test('the app counts a play when an episode starts', function () {
    $podcast = aPodcast(null, 'approved');
    $episode = anEpisode($podcast);
    $pending = anEpisode(aPodcast(null, 'pending'));
    Sanctum::actingAs(member(), ['member']);

    $this->postJson(route('api.v1.podcasts.played', $episode))->assertOk()->assertJson(['plays' => 1]);
    $this->postJson(route('api.v1.podcasts.played', $episode))->assertOk()->assertJson(['plays' => 2]);
    $this->postJson(route('api.v1.podcasts.played', $pending))->assertNotFound();
});

test('notes taken while listening belong to the episode, as podcast notes', function () {
    $episode = anEpisode(aPodcast(null, 'approved'));
    Sanctum::actingAs(member(), ['member']);

    $this->postJson(route('api.v1.notes.store'), ['context' => 'podcast:'.$episode->id, 'title' => '', 'body' => 'Faith is trusting before I see.', 'reference' => 'Morning Manna: Walking by faith'])
        ->assertSuccessful()->assertJsonPath('data.kind', 'podcast')->assertJsonPath('data.kind_label', 'Podcast');
    $this->getJson(route('api.v1.notes.index', ['kind' => 'podcast']))->assertOk()->assertJsonCount(1, 'data');
});
