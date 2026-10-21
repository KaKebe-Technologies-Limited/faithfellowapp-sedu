<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\RadioStation;
use App\Models\RadioTrack;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The FaithFellows radio channels: the platform's Gospel radio (songs) and Podcast radio (teaching), and one channel for each church. A channel plays
 * what has been added to it, in order, round the clock, in a loop. Everyone hears the same thing at the same moment, because "what is playing now"
 * is worked out from the clock: the loop's total length and the time since a fixed starting point. "Play now" puts a chosen track on air at once by
 * anchoring the channel to it: the loop then runs from that track, from that moment, so every listener moves to it together.
 */
class RadioService
{
    /** The clock starts here; changing it would shuffle where every listener is in every loop. */
    public const EPOCH = '2026-01-01 00:00:00';

    /** Formats phones can play without extra codecs. */
    public const EXTENSIONS = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'wav' => 'audio/wav'];

    // The Gospel radio keeps its name and its switch in the platform settings (as it always did); every other channel keeps them in its own row.

    public function enabled(?RadioStation $station = null): bool
    {
        $station ??= RadioStation::gospel();

        return $station->isGospel() ? (bool) PlatformSetting::get('radio_enabled', '1') : (bool) $station->is_enabled;
    }

    /** On, and not switched off by the platform (a church channel can be blocked by the super admin). */
    public function available(?RadioStation $station = null): bool
    {
        $station ??= RadioStation::gospel();

        return $this->enabled($station) && ! $station->is_blocked;
    }

    public function name(?RadioStation $station = null): string
    {
        $station ??= RadioStation::gospel();

        return $station->isGospel() ? (string) (PlatformSetting::get('radio_name') ?: 'FaithFellows Radio') : (string) $station->name;
    }

    public function saveSettings(string $name, bool $enabled, ?RadioStation $station = null): void
    {
        $station ??= RadioStation::gospel();
        $name = trim($name);
        if ($station->isGospel()) {
            PlatformSetting::put('radio_name', $name ?: 'FaithFellows Radio');
            PlatformSetting::put('radio_enabled', $enabled ? '1' : '0');

            return;
        }
        $station->update(['name' => $name ?: $station->name, 'is_enabled' => $enabled]);
    }

    /** @return Collection<int, RadioTrack> what is in the channel's loop, in order */
    public function tracks(?RadioStation $station = null): Collection
    {
        return RadioTrack::onAir()->ofStation($station ?? RadioStation::gospel())->get()->values();
    }

    public function loopSeconds(?RadioStation $station = null): int
    {
        return (int) RadioTrack::onAir()->ofStation($station ?? RadioStation::gospel())->sum('duration_seconds');
    }

    /**
     * Where in the loop the channel is at a moment: from the track an admin put on air with "Play now" (and the time they did it) when that track is
     * still in the loop, otherwise from the fixed starting point of the clock.
     *
     * @param  Collection<int, RadioTrack>  $tracks
     */
    protected function secondsIntoLoop(RadioStation $station, Collection $tracks, CarbonInterface $at): int
    {
        $anchor = $this->anchor($station);
        $index = $anchor ? $tracks->search(fn (RadioTrack $t) => $t->id === $anchor['track']) : false;
        if ($index === false) {
            return $at->getTimestamp() - strtotime(self::EPOCH.' UTC');
        }

        return (int) $tracks->take($index)->sum('duration_seconds') + $at->getTimestamp() - $anchor['at'];
    }

    /** @return array{track: int, at: int}|null the track an admin last put on air, and when */
    public function anchor(RadioStation $station): ?array
    {
        [$track, $at] = array_pad(explode(':', (string) PlatformSetting::get('radio_anchor_'.$station->slug), 2), 2, null);

        return ctype_digit((string) $track) && ctype_digit((string) $at) ? ['track' => (int) $track, 'at' => (int) $at] : null;
    }

    /**
     * "Play now": puts this track on air this second. It is switched on if it was off, the channel carries on from it in loop order, and every
     * phone that is listening moves to it within a few seconds.
     */
    public function playNow(RadioTrack $track): void
    {
        if (! $track->is_active) {
            $track->update(['is_active' => true]);
        }
        PlatformSetting::put('radio_anchor_'.$track->channel()->slug, $track->id.':'.now()->getTimestamp());
    }

    /**
     * What a channel is playing at a moment (default: now), with the tracks that follow and the ones just played.
     *
     * @return array<string, mixed>|null null when the channel is off or has nothing in it
     */
    public function nowPlaying(?CarbonInterface $at = null, int $upcoming = 4, int $recent = 5, ?RadioStation $station = null): ?array
    {
        $station ??= RadioStation::gospel();
        $tracks = $this->available($station) ? $this->tracks($station) : collect();
        if ($tracks->isEmpty()) {
            return null;
        }

        $at ??= now();
        $total = (int) $tracks->sum('duration_seconds');
        $phase = ($this->secondsIntoLoop($station, $tracks, $at) % $total + $total) % $total;

        $index = 0;
        $offset = 0;
        $walked = 0;
        foreach ($tracks as $i => $track) {
            if ($phase < $walked + $track->duration_seconds) {
                $index = $i;
                $offset = $phase - $walked;
                break;
            }
            $walked += $track->duration_seconds;
        }

        $count = $tracks->count();
        $startedAt = $at->copy()->subSeconds($offset);

        $next = [];
        $time = $startedAt->copy()->addSeconds($tracks[$index]->duration_seconds);
        for ($k = 1; $k <= $upcoming; $k++) {
            $track = $tracks[($index + $k) % $count];
            $next[] = $track->toPayload() + ['starts_at' => $time->toIso8601String()];
            $time = $time->copy()->addSeconds($track->duration_seconds);
        }

        $before = [];
        $time = $startedAt->copy();
        for ($k = 1; $k <= $recent; $k++) {
            $track = $tracks[(($index - $k) % $count + $count) % $count];
            $time = $time->copy()->subSeconds($track->duration_seconds);
            $before[] = $track->toPayload() + ['played_at' => $time->toIso8601String()];
        }

        return [
            'track' => $tracks[$index]->toPayload(),
            'offset' => $offset,
            'started_at' => $startedAt->toIso8601String(),
            'ends_at' => $startedAt->copy()->addSeconds($tracks[$index]->duration_seconds)->toIso8601String(),
            'upcoming' => $next,
            'recent' => $before,
        ];
    }

    /**
     * Everything the app needs to show one channel: its name, what is on and what is next.
     *
     * @return array<string, mixed>
     */
    public function payload(RadioStation $station, bool $allowFallback = true): array
    {
        $now = $this->nowPlaying(null, 4, 5, $station);

        // Nothing is on this channel (it is empty or switched off): the Podcast radio plays instead, so the radio always has something to hear.
        if ($now === null && $allowFallback && $station->slug !== RadioStation::PODCAST && RadioStation::ready()) {
            $podcast = RadioStation::podcast();
            if ($this->nowPlaying(null, 1, 0, $podcast) !== null) {
                return array_merge($this->payload($podcast, false), ['fallback' => true, 'fallback_for' => ['slug' => $station->slug, 'name' => $this->name($station)]]);
            }
        }

        return [
            'fallback' => false,
            'fallback_for' => null,
            'id' => $station->exists ? $station->id : null,
            'slug' => $station->slug,
            'kind' => $station->kind,
            'enabled' => $now !== null,
            'name' => $this->name($station),
            'tagline' => $station->tagline(),
            'church' => $station->church_id && $station->relationLoaded('church') && $station->church
                ? ['id' => $station->church->id, 'name' => $station->church->name]
                : null,
            'now' => $now,
            'songs' => $this->tracks($station)->count(),
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * The channels this person can listen to: the platform's, and the channels of the churches they follow or work for. Only channels that are on
     * and have something in them. The Gospel radio comes first.
     *
     * @return Collection<int, RadioStation>
     */
    public function stationsFor(?User $user): Collection
    {
        $stations = collect([RadioStation::gospel()]);
        if (RadioStation::ready()) {
            $stations->push(RadioStation::podcast());
            $churchIds = $this->churchIdsOf($user);
            if ($churchIds->isNotEmpty()) {
                $stations = $stations->merge(RadioStation::whereIn('church_id', $churchIds)->with('church')->orderBy('name')->get());
            }
        }

        return $stations->filter(fn (RadioStation $s) => $this->available($s) && $this->tracks($s)->isNotEmpty())->values();
    }

    /** The platform's channels are for everyone; a church's channel is for the people who follow that church or work for it. */
    public function canListen(?User $user, RadioStation $station): bool
    {
        if ($station->isPlatform()) {
            return true;
        }

        return $user !== null && ($user->isSuperAdmin() || $this->churchIdsOf($user)->contains($station->church_id));
    }

    /** @return Collection<int, int> the churches a person follows or works for */
    protected function churchIdsOf(?User $user): Collection
    {
        return $user
            ? $user->subscribedChurches()->pluck('churches.id')->merge($user->staffMemberships()->where('status', 'active')->pluck('church_id'))->unique()->values()
            : collect();
    }

    // ── Adding and arranging songs ──────────────────────────────────────────────────────────────────────

    /**
     * Adds an audio file to a channel's loop (the Gospel radio unless a channel is given). Title, artist, length and cover picture are read from
     * the file itself.
     *
     * @throws InvalidArgumentException when the file is not audio the phones can play
     */
    public function addFile(string $path, string $originalName, ?User $by = null, ?string $title = null, ?string $artist = null, ?RadioStation $station = null): RadioTrack
    {
        $station ??= RadioStation::gospel();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (! isset(self::EXTENSIONS[$extension])) {
            throw new InvalidArgumentException(__('Use an MP3, M4A, AAC or WAV file.'));
        }

        $info = (new \getID3)->analyze($path);
        $seconds = (int) round((float) ($info['playtime_seconds'] ?? 0));
        if ($seconds < 1 || empty($info['audio'])) {
            throw new InvalidArgumentException(__('This does not look like a song file we can read.'));
        }
        \getid3_lib::CopyTagsToComments($info);

        [$fileTitle, $fileArtist] = $this->namesFromFilename($originalName);
        $artist = trim((string) ($artist ?: ($info['comments']['artist'][0] ?? '') ?: $fileArtist));
        $title = $this->cleanTitle(trim((string) ($title ?: ($info['comments']['title'][0] ?? '') ?: $fileTitle)), $artist);

        $name = (string) Str::uuid();
        $stored = "radio/{$name}.{$extension}";
        Storage::disk('local')->put($stored, fopen($path, 'rb'));

        $track = RadioTrack::create([
            ...($station->exists ? ['station_id' => $station->id] : []),
            'title' => Str::limit($title ?: pathinfo($originalName, PATHINFO_FILENAME), 160, ''),
            'artist' => $artist !== '' ? Str::limit($artist, 160, '') : null,
            'file_path' => $stored,
            'mime' => self::EXTENSIONS[$extension],
            'size' => filesize($path),
            'duration_seconds' => $seconds,
            'cover_path' => $this->saveCover($info['comments']['picture'][0]['data'] ?? null, $name),
            'position' => (int) RadioTrack::ofStation($station)->max('position') + 1,
            'is_active' => true,
            'uploaded_by' => $by?->id,
        ]);
        // A new teaching on the Podcast radio pops up on members' phones.
        rescue(fn () => app(ContentPushes::class)->radioTrack($track), 0, false);

        return $track;
    }

    /** Moves a track one place earlier or later in its channel's loop. */
    public function move(RadioTrack $track, string $direction): void
    {
        $ids = RadioTrack::ofStation($track->channel())->orderBy('position')->orderBy('id')->pluck('id')->all();
        $i = array_search($track->id, $ids, true);
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($i === false || ! isset($ids[$j])) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $this->renumber($ids);
    }

    public function delete(RadioTrack $track): void
    {
        $channel = $track->channel();
        Storage::disk('local')->delete(array_filter([$track->file_path, $track->cover_path]));
        $track->delete();
        $this->renumber(RadioTrack::ofStation($channel)->orderBy('position')->orderBy('id')->pluck('id')->all());
    }

    /** @param  array<int, int>  $ids */
    protected function renumber(array $ids): void
    {
        foreach (array_values($ids) as $position => $id) {
            RadioTrack::whereKey($id)->update(['position' => $position + 1]);
        }
    }

    // ── Reading the file's names ────────────────────────────────────────────────────────────────────────

    /** "I Surrender - Hillsong Worship - (128 Kbps).mp3" → ["I Surrender", "Hillsong Worship"] */
    protected function namesFromFilename(string $filename): array
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = trim(preg_replace(['/\(\s*\d+\s*kbps\s*\)/i', '/[\s_]+/'], ['', ' '], $base), ' -');
        $parts = array_map('trim', explode(' - ', $base, 2));

        return [$parts[0] ?? $base, $parts[1] ?? ''];
    }

    /** Drops "- Artist" from the end of a title that repeats the artist. */
    protected function cleanTitle(string $title, string $artist): string
    {
        if ($artist !== '' && Str::endsWith(Str::lower($title), Str::lower(' - '.$artist))) {
            return trim(Str::substr($title, 0, -Str::length(' - '.$artist)));
        }

        return $title;
    }

    /** The picture inside the song file, resized to at most 800 pixels and saved as a JPEG. Null when there is none. */
    protected function saveCover(?string $data, string $name): ?string
    {
        if (! $data || ! function_exists('imagecreatefromstring') || ! ($image = @imagecreatefromstring($data))) {
            return null;
        }
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, 800 / max($w, $h));
        $resized = imagecreatetruecolor((int) round($w * $scale), (int) round($h * $scale));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $w, $h);

        ob_start();
        imagejpeg($resized, null, 84);
        $jpeg = (string) ob_get_clean();
        $path = "radio/covers/{$name}.jpg";
        Storage::disk('local')->put($path, $jpeg);

        return $path;
    }
}
