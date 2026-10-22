<?php

namespace App\Services;

use App\Models\Podcast;
use App\Models\User;
use App\Support\ContactGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use SimpleXMLElement;

/**
 * Brings a podcast over from the RSS feed of the host it already uses (Podbean, Buzzsprout, Anchor…): the name, who
 * presents it, the summary, the cover and every episode. The audio stays on the host and plays from the link in the
 * feed. A connected podcast is checked again from time to time so new episodes appear by themselves.
 */
class PodcastFeedService
{
    public const MAX_BYTES = 6 * 1024 * 1024;

    public const MAX_EPISODES = 300;

    protected const ITUNES = 'http://www.itunes.com/dtds/podcast-1.0.dtd';

    protected const CONTENT = 'http://purl.org/rss/1.0/modules/content/';

    /** @var array<string, bool> */
    protected array $privateHosts = [];

    public function __construct(protected PodcastService $podcasts) {}

    // ── Reading a feed ──────────────────────────────────────────────────────────────────────────────────

    /**
     * Fetches and reads a feed.
     *
     * @return array{channel: array<string, mixed>, items: list<array<string, mixed>>}
     *
     * @throws PodcastFeedException with a message that can be shown to the podcaster
     */
    public function read(string $url): array
    {
        $body = $this->download($url);
        if (preg_match('/<!ENTITY/i', $body)) {
            throw new PodcastFeedException(__('That feed cannot be read.'));
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $xml || ! isset($xml->channel->title)) {
            throw new PodcastFeedException(__('That address is not a podcast RSS feed. Copy the RSS feed link from your podcast host.'));
        }

        $channel = $xml->channel;
        $itunes = $channel->children(self::ITUNES);
        $owner = $itunes->owner ? $itunes->owner->children(self::ITUNES) : null;

        $items = [];
        foreach ($channel->item as $item) {
            if (count($items) >= self::MAX_EPISODES) {
                break;
            }
            if ($episode = $this->readItem($item, count($items) + 1)) {
                $items[] = $episode;
            }
        }

        return [
            'channel' => [
                'title' => $this->text((string) $channel->title, 160),
                'author' => $this->text((string) $itunes->author, 120) ?: $this->text((string) ($owner?->name), 120),
                'description' => $this->text((string) $channel->description) ?: $this->text((string) $itunes->summary),
                'link' => $this->url((string) $channel->link),
                'language' => strtolower(substr((string) $channel->language, 0, 2)),
                'image' => $this->url((string) ($itunes->image ? $itunes->image->attributes()->href : '')) ?: $this->url((string) $channel->image->url),
                'categories' => $this->categories($channel),
            ],
            'items' => $items,
        ];
    }

    protected function download(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            throw new PodcastFeedException(__('Use the full https link of your podcast RSS feed.'));
        }
        if ($this->isPrivate($host)) {
            throw new PodcastFeedException(__('That link cannot be used.'));
        }

        try {
            $response = Http::timeout(15)->connectTimeout(6)
                ->withHeaders(['User-Agent' => 'FaithFellows podcast import', 'Accept' => 'application/rss+xml, application/xml, text/xml;q=0.9, */*;q=0.5'])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3, 'protocols' => ['https'],
                        'on_redirect' => function ($request, ResponseInterface $response, UriInterface $uri) {
                            if ($this->isPrivate(strtolower($uri->getHost()))) {
                                throw new PodcastFeedException(__('That link cannot be used.'));
                            }
                        },
                    ],
                    'on_headers' => function (ResponseInterface $response) {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                            throw new PodcastFeedException(__('That feed is too large to import.'));
                        }
                    },
                ])->get($url);
        } catch (PodcastFeedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof PodcastFeedException) {
                throw $previous;
            }
            throw new PodcastFeedException(__('We could not reach that feed. Check the link and try again.'));
        }

        if (! $response->successful()) {
            throw new PodcastFeedException(__('That feed could not be opened (:code). Check the link and try again.', ['code' => $response->status()]));
        }
        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            throw new PodcastFeedException(__('That feed is too large to import.'));
        }

        return $body;
    }

    /** @return array<string, mixed>|null */
    protected function readItem(SimpleXMLElement $item, int $position): ?array
    {
        $itunes = $item->children(self::ITUNES);
        $content = $item->children(self::CONTENT);

        $enclosure = $item->enclosure;
        $audio = $enclosure ? trim((string) $enclosure['url']) : '';
        $type = $enclosure ? strtolower((string) $enclosure['type']) : '';
        $path = strtolower((string) parse_url($audio, PHP_URL_PATH));
        $isAudio = str_starts_with($type, 'audio/') || (! str_starts_with($type, 'video/') && preg_match('/\.(mp3|m4a|aac|wav|ogg|oga|opus)$/', $path));
        if ($audio === '' || ! $isAudio) {
            return null;
        }

        $when = null;
        try {
            $when = trim((string) $item->pubDate) !== '' ? Carbon::parse((string) $item->pubDate)->setTimezone(config('app.timezone')) : null;
        } catch (\Throwable) {
            // An unreadable date: the episode counts as published now.
        }

        return [
            'guid' => Str::limit(trim((string) $item->guid) ?: $audio, 250, ''),
            'title' => $this->text((string) $item->title, 180) ?: $this->text((string) $itunes->title, 180) ?: __('Episode :n', ['n' => $position]),
            'summary' => $this->text((string) $content->encoded, 5000) ?: $this->text((string) $item->description, 5000) ?: $this->text((string) $itunes->summary, 5000),
            'audio_url' => $audio,
            'duration' => $this->duration((string) $itunes->duration),
            'season' => ((int) $itunes->season) ?: null,
            'number' => ((int) $itunes->episode) ?: null,
            'published_at' => $when,
        ];
    }

    /** @return list<string> */
    protected function categories(SimpleXMLElement $channel): array
    {
        $found = [];
        foreach ($channel->children(self::ITUNES)->category as $category) {
            $found[] = (string) $category->attributes()->text;
            foreach ($category->children(self::ITUNES)->category as $sub) {
                $found[] = (string) $sub->attributes()->text;
            }
        }
        foreach ($channel->category as $category) {
            $found[] = (string) $category;
        }

        return array_values(array_filter(array_map('trim', $found)));
    }

    /** The best matching FaithFellows category for the host's category names. */
    public function guessCategory(array $names): string
    {
        $text = strtolower(implode(' ', $names));
        $rules = ['kids' => 'kid|children', 'youth' => 'youth|teen', 'family' => 'marriage|family|parent', 'prayer' => 'prayer', 'worship' => 'music|worship|praise', 'sermons' => 'sermon|preach',
            'leadership' => 'leader', 'missions' => 'mission|outreach', 'interviews' => 'interview', 'testimonies' => 'testimon|stories|journal', 'motivation' => 'motivat|self-help|self help|self-improvement|mindset|success', 'devotional' => 'devotion|inspiration', 'faith' => 'faith', 'teaching' => 'christian|bible|scripture|religion|spiritual|teach'];
        foreach ($rules as $key => $pattern) {
            if (preg_match('/'.$pattern.'/', $text)) {
                return $key;
            }
        }

        return 'teaching';
    }

    // ── Importing and keeping in step ───────────────────────────────────────────────────────────────────

    /**
     * Makes a new podcast from a feed and imports its episodes. A podcast made by the super admin is approved at once;
     * anyone else's waits for review like every other podcast.
     *
     * @param  array{host_name?: string, category?: string}  $with
     *
     * @throws PodcastFeedException
     */
    public function import(User $owner, string $url, array $with = []): Podcast
    {
        $feed = $this->read($url);
        $channel = $feed['channel'];
        if ($channel['title'] === '') {
            throw new PodcastFeedException(__('That feed has no podcast name.'));
        }

        $description = ContactGuard::found($channel['description']) ? '' : $channel['description'];
        $language = array_key_exists($channel['language'], config('faithfellows.languages')) ? $channel['language'] : 'en';
        $approved = $owner->isSuperAdmin();

        $podcast = $owner->podcasts()->create([
            'name' => $channel['title'],
            'host_name' => $with['host_name'] ?? ($channel['author'] ?: $owner->name),
            'summary' => Str::limit($description, 500, '') ?: __('Episodes from :name.', ['name' => $channel['title']]),
            'description' => strlen($description) > 500 ? Str::limit($description, 5000, '') : null,
            'category' => $with['category'] ?? $this->guessCategory($channel['categories']),
            'language' => $language,
            'website_url' => $channel['link'],
            'feed_url' => $url,
            'status' => $approved ? 'approved' : 'pending',
            'approved_at' => $approved ? now() : null,
        ]);
        $this->importCover($podcast, $channel['image']);
        $this->apply($podcast, $feed['items']);
        $podcast->forceFill(['feed_synced_at' => now(), 'feed_error' => null])->saveQuietly();

        return $podcast->refresh();
    }

    /**
     * Reads the feed again and brings the episodes up to date: new ones are added, changed ones updated, and ones the
     * host removed are taken out of the app. Never throws; a problem is kept on the podcast so the podcaster sees it.
     *
     * @return array{added: int, updated: int, removed: int, skipped: int, error: string|null}
     */
    public function sync(Podcast $podcast): array
    {
        try {
            $feed = $this->read((string) $podcast->feed_url);
        } catch (PodcastFeedException $e) {
            $podcast->forceFill(['feed_error' => Str::limit($e->getMessage(), 290, '')])->saveQuietly();

            return ['added' => 0, 'updated' => 0, 'removed' => 0, 'skipped' => 0, 'error' => $e->getMessage()];
        }

        $result = $this->apply($podcast, $feed['items']);
        $podcast->forceFill(['feed_synced_at' => now(), 'feed_error' => null])->saveQuietly();
        $this->importCover($podcast, $feed['channel']['image']);

        return $result + ['error' => null];
    }

    /**
     * Syncs the connected podcasts that have not been checked lately. Used by the scheduled command.
     *
     * @return array{podcasts: int, added: int, errors: int}
     */
    public function syncDue(int $minutes = 30, int $limit = 100): array
    {
        $podcasts = Podcast::whereNotNull('feed_url')->whereIn('status', ['pending', 'approved'])
            ->where(fn ($q) => $q->whereNull('feed_synced_at')->orWhere('feed_synced_at', '<=', now()->subMinutes($minutes)))
            ->orderBy('feed_synced_at')->limit($limit)->get();

        $total = ['podcasts' => 0, 'added' => 0, 'errors' => 0];
        foreach ($podcasts as $podcast) {
            $result = $this->sync($podcast);
            $total['podcasts']++;
            $total['added'] += $result['added'];
            $total['errors'] += $result['error'] ? 1 : 0;
        }

        return $total;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{added: int, updated: int, removed: int, skipped: int}
     */
    protected function apply(Podcast $podcast, array $items): array
    {
        $result = ['added' => 0, 'updated' => 0, 'removed' => 0, 'skipped' => 0];
        $firstRead = $podcast->feed_synced_at === null;
        $added = collect();
        $known = $podcast->episodes()->withTrashed()->whereNotNull('guid')->get()->keyBy('guid');
        $seen = [];

        foreach ($items as $item) {
            if ($this->podcasts->problemWithAddress($item['audio_url'])) {
                $result['skipped']++;

                continue;
            }
            $seen[] = $item['guid'];
            $summary = ContactGuard::found($item['summary']) ? null : (Str::limit($item['summary'], 5000, '') ?: null);
            $fields = [
                'title' => $item['title'], 'summary' => $summary, 'audio_url' => Str::limit($item['audio_url'], 600, ''), 'duration_seconds' => $item['duration'],
                'season' => $item['season'], 'number' => $item['number'], 'published_at' => $item['published_at'] ?? now(),
            ];

            $episode = $known[$item['guid']] ?? null;
            if (! $episode) {
                $added->push($podcast->episodes()->create($fields + ['guid' => $item['guid'], 'status' => 'published']));
                $result['added']++;
            } elseif (! $episode->trashed()) {
                // The host is the source of truth; an episode the podcaster put back on after the host removed it is published again.
                $episode->fill($fields + ['status' => 'published']);
                if ($episode->isDirty()) {
                    $episode->save();
                    $result['updated']++;
                }
            }
        }

        // Episodes the host no longer lists leave the app (kept as drafts). An empty feed removes nothing: it is more likely a glitch.
        if ($seen !== []) {
            foreach ($known as $guid => $episode) {
                if (! in_array($guid, $seen, true) && ! $episode->trashed() && $episode->status === 'published') {
                    $episode->update(['status' => 'draft']);
                    $result['removed']++;
                }
            }
        }
        $podcast->refreshCounts();

        // A podcast read for the first time is not news; after that the newest new episode pops up on members' phones.
        if (! $firstRead && $added->isNotEmpty()) {
            rescue(fn () => app(ContentPushes::class)->episode($added->sortByDesc('published_at')->first()), 0, false);
        }

        return $result;
    }

    /** The feed's picture becomes the cover when the podcast has none yet. Best effort: a missing picture is never an error. */
    protected function importCover(Podcast $podcast, ?string $imageUrl): void
    {
        if (! $imageUrl || str_starts_with($imageUrl, 'http://') || $podcast->getFirstMedia('cover')) {
            return;
        }
        try {
            $host = strtolower((string) parse_url($imageUrl, PHP_URL_HOST));
            if ($host === '' || $this->isPrivate($host)) {
                return;
            }
            $response = Http::timeout(10)->withHeaders(['User-Agent' => 'FaithFellows podcast import'])->get($imageUrl);
            $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$type] ?? null;
            if (! $response->successful() || ! $extension || strlen($response->body()) > 4 * 1024 * 1024 || strlen($response->body()) < 200) {
                return;
            }
            $podcast->addMediaFromString($response->body())->usingFileName('cover.'.$extension)->toMediaCollection('cover');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ── Small readers ───────────────────────────────────────────────────────────────────────────────────

    protected function isPrivate(string $host): bool
    {
        return $this->privateHosts[$host] ??= $this->podcasts->isPrivateHost($host);
    }

    /** Plain text from the host's HTML: paragraphs become line breaks, tags and entities go. */
    protected function text(string $html, int $max = 5000): string
    {
        $html = preg_replace('#</(p|div|li|h[1-6])>|<br\s*/?>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace(["/[ \t]+/", "/\s*\n\s*/"], [' ', "\n"], $text) ?? $text);

        return Str::limit($text, $max, '');
    }

    protected function url(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' && preg_match('#^https?://#i', $value) ? Str::limit($value, 300, '') : null;
    }

    /** iTunes durations are seconds ("443") or clock style ("07:23", "1:02:03"). */
    protected function duration(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^\d+(:\d{1,2}){0,2}$/', $value)) {
            return null;
        }
        $parts = array_map('intval', explode(':', $value));
        $seconds = match (count($parts)) {
            1 => $parts[0],
            2 => $parts[0] * 60 + $parts[1],
            default => $parts[0] * 3600 + $parts[1] * 60 + $parts[2],
        };

        return $seconds > 0 ? $seconds : null;
    }
}
