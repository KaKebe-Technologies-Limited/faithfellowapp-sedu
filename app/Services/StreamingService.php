<?php

namespace App\Services;

use App\Models\Church;
use App\Models\LiveStream;
use App\Models\LiveStreamViewer;
use App\Models\Sermon;
use App\Notifications\Live\LiveStreamStarted;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Live streaming: stream keys, RTMP ingest / HLS playback URLs, going live,
 * viewer counts and archiving recordings as sermons.
 *
 * Providers:
 *  - self_hosted: nginx-rtmp ingest → HLS at {hls_base}/{key}.m3u8 and an
 *    audio-only rendition at {hls_base}/{key}_audio.m3u8 (≈1/10 of the data).
 *  - cloudflare: Cloudflare Stream live inputs (same URLs shape via config).
 *  - external: YouTube / Facebook embeds or any HLS URL.
 */
class StreamingService
{
    public const VIEWER_WINDOW_SECONDS = 75;

    public function __construct(protected NotificationService $notifications) {}

    public function rotateKey(LiveStream $stream): string
    {
        $key = 'ff_'.$stream->church_id.'_'.Str::lower(Str::random(24));
        $stream->forceFill(['stream_key' => $key, 'stream_key_hash' => hash('sha256', $key)])->save();
        Audit::log('live.key_rotated', $stream, $stream->title);

        return $key;
    }

    public function ingestUrl(): string
    {
        return rtrim(config('services.streaming.rtmp_url'), '/');
    }

    /**
     * @return array{video: ?string, audio: ?string, embed: ?string}
     */
    public function playback(LiveStream $stream): array
    {
        if ($stream->source_type === 'youtube' && preg_match('~(?:v=|youtu\.be/|live/|embed/)([\w-]{11})~', (string) $stream->external_url, $m)) {
            return ['video' => null, 'audio' => null, 'embed' => 'https://www.youtube-nocookie.com/embed/'.$m[1].'?autoplay=1'];
        }
        if ($stream->source_type === 'facebook') {
            return ['video' => null, 'audio' => null, 'embed' => 'https://www.facebook.com/plugins/video.php?href='.urlencode((string) $stream->external_url)];
        }
        if ($stream->source_type === 'hls') {
            return ['video' => $stream->external_url, 'audio' => $stream->external_url, 'embed' => null];
        }

        $key = $stream->stream_key;
        $base = rtrim(config('services.streaming.hls_base_url'), '/');

        return $key
            ? ['video' => "$base/$key.m3u8", 'audio' => "$base/{$key}_audio.m3u8", 'embed' => null]
            : ['video' => null, 'audio' => null, 'embed' => null];
    }

    public function start(LiveStream $stream): LiveStream
    {
        if ($stream->isLive()) {
            return $stream;
        }

        $stream->update(['status' => 'live', 'started_at' => now(), 'ended_at' => null]);
        Audit::log('live.started', $stream, $stream->title);

        if (! $stream->notified_at) {
            $stream->forceFill(['notified_at' => now()])->saveQuietly();
            $stream->loadMissing('church');
            $this->notifications->notifyChurch($stream->church, new LiveStreamStarted($stream));
        }

        return $stream;
    }

    public function stop(LiveStream $stream): LiveStream
    {
        if (! $stream->isLive()) {
            return $stream;
        }

        $stream->update([
            'status' => 'ended',
            'ended_at' => now(),
            'total_viewers' => $stream->viewers()->count(),
        ]);
        Audit::log('live.ended', $stream, $stream->title);

        // Self-hosted recordings land at {hls_base}/recordings/{key}.mp4 (nginx-rtmp record).
        if (! $stream->recording_url && $stream->source_type === 'rtmp' && $stream->stream_key) {
            $stream->forceFill(['recording_url' => rtrim(config('services.streaming.hls_base_url'), '/').'/recordings/'.$stream->stream_key.'.mp4'])->saveQuietly();
        }
        if (in_array($stream->source_type, ['youtube', 'facebook'], true) && ! $stream->recording_url) {
            $stream->forceFill(['recording_url' => $stream->external_url])->saveQuietly();
        }

        return $stream;
    }

    /**
     * Auto-archive: publish the recording as a sermon.
     */
    public function archiveAsSermon(LiveStream $stream, array $overrides = []): Sermon
    {
        return DB::transaction(function () use ($stream, $overrides) {
            $sermon = $stream->sermon ?? Sermon::create([
                'church_id' => $stream->church_id,
                'branch_id' => $stream->branch_id,
                'user_id' => auth()->id() ?? $stream->user_id,
                'title' => $overrides['title'] ?? $stream->title,
                'preacher' => $overrides['preacher'] ?? null,
                'scripture' => $overrides['scripture'] ?? null,
                'description' => $stream->description ? '<p>'.e($stream->description).'</p>' : null,
                'video_url' => $stream->recording_url,
                'duration_seconds' => $stream->durationMinutes() * 60 ?: null,
                'language' => 'en',
                'status' => 'published',
                'visibility' => 'public',
                'published_at' => now(),
                'preached_on' => ($stream->started_at ?? now())->toDateString(),
                'notified_at' => now(), // members were already alerted when the service went live
            ]);

            $stream->update(['status' => 'archived', 'sermon_id' => $sermon->id]);
            Audit::log('live.archived', $stream, $sermon->title);

            return $sermon;
        });
    }

    /**
     * Viewer heartbeat → current viewer count (and peak tracking).
     */
    public function heartbeat(LiveStream $stream, string $viewerKey, ?int $userId, bool $audioOnly = false): int
    {
        $now = now();
        LiveStreamViewer::upsert([[
            'church_id' => $stream->church_id,
            'live_stream_id' => $stream->id,
            'user_id' => $userId,
            'viewer_key' => $viewerKey,
            'audio_only' => $audioOnly,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]], ['live_stream_id', 'viewer_key'], ['last_seen_at', 'audio_only', 'user_id']);

        $current = $this->currentViewers($stream);
        if ($current > $stream->peak_viewers) {
            $stream->newQuery()->whereKey($stream->id)->update(['peak_viewers' => $current]);
        }

        return $current;
    }

    public function currentViewers(LiveStream $stream): int
    {
        return $stream->viewers()->where('last_seen_at', '>=', now()->subSeconds(self::VIEWER_WINDOW_SECONDS))->count();
    }

    /**
     * Stream hours used this month vs the plan limit.
     *
     * @return array{used: float, limit: int}
     */
    public function monthlyUsage(Church $church): array
    {
        $minutes = $church->liveStreams()->whereNotNull('started_at')->where('started_at', '>=', now()->startOfMonth())->get()
            ->sum(fn (LiveStream $s) => $s->durationMinutes());

        $limit = method_exists($church, 'planLimit') ? ($church->planLimit('stream_hours') ?? config('faithfellows.limits.stream_hours')) : config('faithfellows.limits.stream_hours');

        return ['used' => round($minutes / 60, 1), 'limit' => (int) $limit];
    }

    /**
     * Find a stream by the key sent by the RTMP server.
     */
    public function findByKey(string $key): ?LiveStream
    {
        return LiveStream::where('stream_key_hash', hash('sha256', $key))->first();
    }
}
