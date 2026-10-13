<?php

use App\Models\Podcast;
use App\Services\PodcastFeedService;
use App\Services\PodcastService;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
| RSS import: a podcaster who already publishes on Podbean or another host pastes the feed link; the podcast and its
| episodes come over (the audio stays on the host) and new episodes are picked up by themselves.
*/

const FEED = 'https://feed.example-host.com/ruth/feed.xml';

beforeEach(function () {
    Cache::forget('platform_settings');
    $GLOBALS['feed'] = ['body' => feedXml(), 'status' => 200];
    Http::fake([
        'feed.example-host.com/*' => fn () => Http::response($GLOBALS['feed']['body'], $GLOBALS['feed']['status'], ['Content-Type' => 'text/xml; charset=UTF-8']),
        'img.example-host.com/*' => Http::response(tinyJpeg(), 200, ['Content-Type' => 'image/jpeg']),
    ]);
});

function tinyJpeg(): string
{
    ob_start();
    imagejpeg(imagecreatetruecolor(300, 300));

    return (string) ob_get_clean();
}

/** @param  list<string>  $items */
function feedXml(?array $items = null, string $description = '<p>Short words from the Bible, every morning.</p>'): string
{
    $items ??= [
        feedItem('Walking by faith', 'guid-1', 'https://mcdn.podbean.com/mf/web/a/walking.mp3', '24:10', 'Mon, 05 Oct 2026 08:00:00 +0000', '<p>Trust before you see.</p>'),
        feedItem('Rest for the weary', 'guid-2', 'https://mcdn.podbean.com/mf/web/b/rest.mp3', '443', 'Mon, 12 Oct 2026 08:00:00 +0000', 'Come to me, all who are weary.'),
    ];

    return '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel>'
        .'<title>Morning Manna</title><link>https://ruth.example-host.com</link><description><![CDATA['.$description.']]></description><language>en</language>'
        .'<itunes:author>Ruth Akello</itunes:author><itunes:image href="https://img.example-host.com/cover.jpg"/>'
        .'<itunes:category text="Religion &amp; Spirituality"><itunes:category text="Christianity"/></itunes:category>'
        .implode('', $items).'</channel></rss>';
}

function feedItem(string $title, string $guid, string $audio, string $duration = '10:00', string $date = 'Mon, 05 Oct 2026 08:00:00 +0000', string $description = '', string $type = 'audio/mpeg'): string
{
    return '<item><title>'.$title.'</title><guid isPermaLink="false">'.$guid.'</guid><pubDate>'.$date.'</pubDate><description><![CDATA['.$description.']]></description>'
        .'<enclosure url="'.$audio.'" length="1000" type="'.$type.'"/><itunes:duration>'.$duration.'</itunes:duration></item>';
}

test('importing a feed makes the podcast with its episodes, its cover and the host text', function () {
    Storage::fake('public');
    $ruth = member(['name' => 'Ruth Akello']);

    $this->actingAs($ruth)->get(route('podcasts.import'))->assertOk()->assertSee('RSS feed link');
    $this->actingAs($ruth)->post(route('podcasts.import.store'), ['feed_url' => FEED, 'host_name' => 'Ruth Akello', 'faith_based' => 1])->assertRedirect();

    $podcast = Podcast::sole();
    expect($podcast->name)->toBe('Morning Manna')->and($podcast->user_id)->toBe($ruth->id)->and($podcast->status)->toBe('pending')
        ->and($podcast->summary)->toBe('Short words from the Bible, every morning.')->and($podcast->category)->toBe('teaching')->and($podcast->language)->toBe('en')
        ->and($podcast->website_url)->toBe('https://ruth.example-host.com')->and($podcast->feed_url)->toBe(FEED)->and($podcast->feed_synced_at)->not->toBeNull()
        ->and($podcast->getFirstMedia('cover'))->not->toBeNull()->and($podcast->episodes_count)->toBe(2);

    $walking = $podcast->episodes()->where('guid', 'guid-1')->sole();
    expect($walking->title)->toBe('Walking by faith')->and($walking->audio_url)->toBe('https://mcdn.podbean.com/mf/web/a/walking.mp3')->and($walking->duration_seconds)->toBe(1450)
        ->and($walking->summary)->toBe('Trust before you see.')->and($walking->status)->toBe('published');
    expect($podcast->episodes()->where('guid', 'guid-2')->value('duration_seconds'))->toBe(443);

    $this->actingAs($ruth)->get(route('podcasts.show', $podcast))->assertOk()->assertSee('Synced from your RSS feed')->assertSee('Check now');
});

test('a podcast the super admin imports is live at once, anyone else waits for review', function () {
    $this->actingAs(superAdmin())->post(route('podcasts.import.store'), ['feed_url' => FEED, 'host_name' => 'Pastor', 'faith_based' => 1])->assertRedirect();
    expect(Podcast::sole()->status)->toBe('approved')->and(Podcast::sole()->approved_at)->not->toBeNull();
});

test('the import is refused for a bad link, a page that is not a feed, a repeat, or without the promise', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $ruth = member();
    $post = fn (array $data) => $this->actingAs($ruth)->post(route('podcasts.import.store'), $data + ['host_name' => 'Ruth', 'faith_based' => 1]);

    $post(['feed_url' => 'http://feed.example-host.com/a.xml'])->assertSessionHasErrors('feed_url');
    $post(['feed_url' => 'https://localhost/feed.xml'])->assertSessionHasErrors('feed_url');
    $post(['feed_url' => FEED, 'faith_based' => 0])->assertSessionHasErrors('faith_based');

    $GLOBALS['feed']['body'] = '<html><body>Not a feed</body></html>';
    $post(['feed_url' => FEED])->assertSessionHasErrors('feed_url');

    $GLOBALS['feed'] = ['body' => '', 'status' => 404];
    $post(['feed_url' => FEED])->assertSessionHasErrors('feed_url');

    $GLOBALS['feed'] = ['body' => feedXml(), 'status' => 200];
    $post(['feed_url' => FEED])->assertRedirect();
    $post(['feed_url' => FEED])->assertSessionHasErrors(['feed_url' => 'This feed is already on FaithFellows.']);
    expect(Podcast::count())->toBe(1);
});

test('feeds that try to read files or are very large are not accepted', function () {
    $ruth = member();
    $GLOBALS['feed']['body'] = '<?xml version="1.0"?><!DOCTYPE rss [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss><channel><title>&x;</title></channel></rss>';
    $this->actingAs($ruth)->post(route('podcasts.import.store'), ['feed_url' => FEED, 'host_name' => 'Ruth', 'faith_based' => 1])->assertSessionHasErrors('feed_url');
    expect(Podcast::count())->toBe(0);
});

test('syncing adds new episodes, updates changed ones and takes out the ones the host removed', function () {
    $ruth = member();
    $this->actingAs($ruth)->post(route('podcasts.import.store'), ['feed_url' => FEED, 'host_name' => 'Ruth', 'faith_based' => 1]);
    $podcast = Podcast::sole();

    $GLOBALS['feed']['body'] = feedXml([
        feedItem('Walking by faith (part one)', 'guid-1', 'https://mcdn.podbean.com/mf/web/a/walking.mp3', '24:10'),
        feedItem('New this week', 'guid-3', 'https://mcdn.podbean.com/mf/web/c/new.mp3', '10:00', 'Mon, 12 Oct 2026 08:00:00 +0000'),
    ]);
    $this->actingAs($ruth)->post(route('podcasts.sync', $podcast))->assertRedirect()->assertSessionHas('success');

    expect($podcast->episodes()->count())->toBe(3)
        ->and($podcast->episodes()->where('guid', 'guid-1')->value('title'))->toBe('Walking by faith (part one)')
        ->and($podcast->episodes()->where('guid', 'guid-2')->value('status'))->toBe('draft')
        ->and($podcast->episodes()->where('guid', 'guid-3')->value('status'))->toBe('published')
        ->and($podcast->fresh()->episodes_count)->toBe(2);

    // Nothing changed: nothing is reported as new.
    expect(app(PodcastFeedService::class)->sync($podcast->fresh()))->toMatchArray(['added' => 0, 'updated' => 0, 'removed' => 0, 'error' => null]);
});

test('an empty or broken feed on a later check keeps the episodes and tells the podcaster', function () {
    $ruth = member();
    $this->actingAs($ruth)->post(route('podcasts.import.store'), ['feed_url' => FEED, 'host_name' => 'Ruth', 'faith_based' => 1]);
    $podcast = Podcast::sole();

    $GLOBALS['feed']['body'] = feedXml([]);
    app(PodcastFeedService::class)->sync($podcast);
    expect($podcast->fresh()->episodes_count)->toBe(2);

    $GLOBALS['feed'] = ['body' => '', 'status' => 500];
    $result = app(PodcastFeedService::class)->sync($podcast->fresh());
    expect($result['error'])->not->toBeNull()->and($podcast->fresh()->feed_error)->not->toBeNull()->and($podcast->fresh()->episodes_count)->toBe(2);
    $this->actingAs($ruth)->get(route('podcasts.show', $podcast))->assertOk()->assertSee($podcast->fresh()->feed_error);
});

test('contact details in a feed are left out, and episodes that are not audio or not on an allowed host are skipped', function () {
    $GLOBALS['feed']['body'] = feedXml([
        feedItem('Good one', 'g1', 'https://mcdn.podbean.com/mf/a.mp3', '5:00', 'Mon, 05 Oct 2026 08:00:00 +0000', 'Call me on 0777676206 to pray together.'),
        feedItem('A video', 'g2', 'https://mcdn.podbean.com/mf/b.mp4', '5:00', 'Mon, 05 Oct 2026 08:00:00 +0000', '', 'video/mp4'),
        feedItem('Elsewhere', 'g3', 'https://random.example.org/c.mp3'),
        feedItem('No sound', 'g4', ''),
    ], 'Write to ruth@example.com or call 0777676206');
    app(PodcastService::class)->saveHosts('podbean.com');

    $this->actingAs(member())->post(route('podcasts.import.store'), ['feed_url' => FEED, 'host_name' => 'Ruth', 'faith_based' => 1])->assertRedirect();
    $podcast = Podcast::sole();

    expect($podcast->summary)->toBe('Episodes from Morning Manna.')
        ->and($podcast->episodes()->pluck('title')->all())->toBe(['Good one'])
        ->and($podcast->episodes()->sole()->summary)->toBeNull();
});

test('the scheduled command checks connected podcasts that have not been checked lately', function () {
    $this->actingAs(member())->post(route('podcasts.import.store'), ['feed_url' => FEED, 'host_name' => 'Ruth', 'faith_based' => 1]);
    $podcast = Podcast::sole();
    $GLOBALS['feed']['body'] = feedXml([feedItem('Fresh', 'g9', 'https://mcdn.podbean.com/mf/f.mp3')]);

    $this->artisan('podcasts:sync-feeds')->assertSuccessful(); // checked a moment ago: left alone
    expect($podcast->episodes()->count())->toBe(2);

    $podcast->forceFill(['feed_synced_at' => now()->subHours(2)])->saveQuietly();
    $this->artisan('podcasts:sync-feeds')->expectsOutputToContain('1 new episode')->assertSuccessful();
    expect($podcast->episodes()->where('guid', 'g9')->exists())->toBeTrue();
});

test('a podcaster can disconnect the feed and keeps the episodes', function () {
    $ruth = member();
    $this->actingAs($ruth)->post(route('podcasts.import.store'), ['feed_url' => FEED, 'host_name' => 'Ruth', 'faith_based' => 1]);
    $podcast = Podcast::sole();

    $this->actingAs(member())->post(route('podcasts.sync', $podcast))->assertNotFound();
    $this->actingAs($ruth)->delete(route('podcasts.feed.disconnect', $podcast))->assertRedirect();
    expect($podcast->fresh()->feed_url)->toBeNull()->and($podcast->episodes()->count())->toBe(2);
    $this->actingAs($ruth)->post(route('podcasts.sync', $podcast))->assertNotFound();
});

// ── On the website ──────────────────────────────────────────────────────────────────────────────────────

test('approved podcasts have a public page with a player for every episode, and pending ones do not', function () {
    $live = Podcast::create(['user_id' => member()->id, 'name' => 'Live One', 'host_name' => 'Ruth', 'summary' => 'A word every day for you.', 'category' => 'devotional', 'language' => 'en', 'status' => 'approved', 'approved_at' => now()]);
    $live->episodes()->create(['title' => 'Out now', 'audio_url' => 'https://mcdn.podbean.com/mf/x.mp3', 'status' => 'published', 'published_at' => now()->subDay(), 'duration_seconds' => 125]);
    $live->episodes()->create(['title' => 'Later', 'audio_url' => 'https://mcdn.podbean.com/mf/y.mp3', 'status' => 'published', 'published_at' => now()->addWeek()]);
    $waiting = Podcast::create(['user_id' => member()->id, 'name' => 'Waiting One', 'host_name' => 'Ruth', 'summary' => 'A word every day for you.', 'category' => 'devotional', 'language' => 'en', 'status' => 'pending']);

    $this->get(route('podcasts.listen'))->assertOk()->assertSee('Live One')->assertDontSee('Waiting One');
    $this->get(route('podcasts.listen', ['q' => 'nothing-like-this']))->assertOk()->assertSee('No podcasts found');
    $this->get(route('podcasts.listen.show', $live->slug))->assertOk()->assertSee('Out now')->assertSee('2:05')->assertSee('<audio', false)->assertDontSee('Later');
    $this->get(route('podcasts.listen.show', $waiting->slug))->assertNotFound();
    $this->get(route('podcasts.embed', $waiting->slug))->assertNotFound();
});

test('the embeddable player can be framed by other websites, other pages cannot', function () {
    $podcast = Podcast::create(['user_id' => member()->id, 'name' => 'Embed Me', 'host_name' => 'Ruth', 'summary' => 'A word every day for you.', 'category' => 'devotional', 'language' => 'en', 'status' => 'approved', 'approved_at' => now()]);
    $podcast->episodes()->create(['title' => 'Hello', 'audio_url' => 'https://mcdn.podbean.com/mf/x.mp3', 'status' => 'published', 'published_at' => now()->subDay()]);

    $embed = $this->get(route('podcasts.embed', $podcast->slug))->assertOk()->assertSee('Embed Me')->assertSee('Hello');
    expect($embed->headers->has('X-Frame-Options'))->toBeFalse();
    expect($this->get(route('podcasts.listen.show', $podcast->slug))->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');

    $this->actingAs($podcast->owner)->get(route('podcasts.show', $podcast))->assertOk()->assertSee('/embed', false)->assertSee('iframe');
});
