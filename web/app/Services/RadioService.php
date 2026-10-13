<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\RadioTrack;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The FaithFellows gospel radio: one station for the whole app that plays the songs the super admin has added,
 * in order, round the clock. Everyone hears the same song at the same moment, because "what is playing now" is
 * worked out from the clock: the loop's total length and the time since a fixed starting point.
 */
class RadioService
{
    /** The station's clock starts here; changing it would shuffle where every listener is in the loop. */
    public const EPOCH = '2026-01-01 00:00:00';

    /** Formats phones can play without extra codecs. */
    public const EXTENSIONS = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'wav' => 'audio/wav'];

    public function enabled(): bool
    {
        return (bool) PlatformSetting::get('radio_enabled', '1');
    }

    public function name(): string
    {
        return (string) (PlatformSetting::get('radio_name') ?: 'FaithFellows Radio');
    }

    public function saveSettings(string $name, bool $enabled): void
    {
        PlatformSetting::put('radio_name', trim($name) ?: 'FaithFellows Radio');
        PlatformSetting::put('radio_enabled', $enabled ? '1' : '0');
    }

    /** @return Collection<int, RadioTrack> the songs in the loop, in order */
    public function tracks(): Collection
    {
        return RadioTrack::onAir()->get()->values();
    }

    public function loopSeconds(): int
    {
        return (int) RadioTrack::onAir()->sum('duration_seconds');
    }

    /**
     * What the station is playing at a moment (default: now), with the songs that follow and the ones just played.
     *
     * @return array<string, mixed>|null null when the radio is off or has no songs
     */
    public function nowPlaying(?CarbonInterface $at = null, int $upcoming = 4, int $recent = 5): ?array
    {
        $tracks = $this->enabled() ? $this->tracks() : collect();
        if ($tracks->isEmpty()) {
            return null;
        }

        $at ??= now();
        $total = (int) $tracks->sum('duration_seconds');
        $phase = (($at->getTimestamp() - strtotime(self::EPOCH.' UTC')) % $total + $total) % $total;

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

    // ── Adding and arranging songs ──────────────────────────────────────────────────────────────────────

    /**
     * Adds a song file to the loop. Title, artist, length and cover picture are read from the file itself.
     *
     * @throws InvalidArgumentException when the file is not a song the phones can play
     */
    public function addFile(string $path, string $originalName, ?User $by = null, ?string $title = null, ?string $artist = null): RadioTrack
    {
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

        return RadioTrack::create([
            'title' => Str::limit($title ?: pathinfo($originalName, PATHINFO_FILENAME), 160, ''),
            'artist' => $artist !== '' ? Str::limit($artist, 160, '') : null,
            'file_path' => $stored,
            'mime' => self::EXTENSIONS[$extension],
            'size' => filesize($path),
            'duration_seconds' => $seconds,
            'cover_path' => $this->saveCover($info['comments']['picture'][0]['data'] ?? null, $name),
            'position' => (int) RadioTrack::max('position') + 1,
            'is_active' => true,
            'uploaded_by' => $by?->id,
        ]);
    }

    /** Moves a song one place earlier or later in the loop. */
    public function move(RadioTrack $track, string $direction): void
    {
        $ids = RadioTrack::orderBy('position')->orderBy('id')->pluck('id')->all();
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
        Storage::disk('local')->delete(array_filter([$track->file_path, $track->cover_path]));
        $track->delete();
        $this->renumber(RadioTrack::orderBy('position')->orderBy('id')->pluck('id')->all());
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
