<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\Podcast;
use App\Models\PodcastEpisode;
use App\Models\User;
use App\Notifications\Podcasts\PodcastReviewed;
use Illuminate\Support\Facades\Http;

/**
 * Podcasts made by members: where their audio may be hosted, whether a pasted link really is audio, and the super
 * admin's review (approve / not now / hide) that decides what listeners see in the app.
 */
class PodcastService
{
    /** Host sites podcasters may use, set by the super admin (comma separated). Empty means any https site. */
    public function allowedHosts(): array
    {
        return collect(explode(',', (string) PlatformSetting::get('podcast_hosts', '')))
            ->map(fn ($h) => strtolower(trim(preg_replace('#^https?://#i', '', trim($h)), " /\t")))->filter()->unique()->values()->all();
    }

    public function saveHosts(string $hosts): void
    {
        PlatformSetting::put('podcast_hosts', collect(explode(',', str_replace(["\n", ';'], ',', $hosts)))->map(fn ($h) => trim($h))->filter()->implode(', '));
    }

    /**
     * Whether an audio link may be added. Returns a message when it may not, or null when it is fine. A link on a
     * site that cannot be reached is accepted (it may be down for a moment), but a web page or a missing file is not.
     */
    public function problemWithLink(string $url): ?string
    {
        if ($problem = $this->problemWithAddress($url)) {
            return $problem;
        }

        try {
            $response = Http::timeout(6)->withHeaders(['User-Agent' => 'FaithFellows link check'])->withOptions(['allow_redirects' => ['max' => 3]])->head($url);
        } catch (\Throwable) {
            return null;
        }
        $type = strtolower((string) $response->header('Content-Type'));

        return match (true) {
            $response->status() === 404 || $response->status() === 410 => __('That link does not exist (not found). Check it and try again.'),
            str_contains($type, 'text/html') => __('That link opens a web page, not an audio file. Use the direct link to the audio (it usually ends in .mp3 or .m4a).'),
            default => null,
        };
    }

    /** The checks that need no network call: https, one of the allowed host sites, and not a private address. */
    public function problemWithAddress(string $url): ?string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            return __('Use the full https link to the audio file.');
        }

        $allowed = $this->allowedHosts();
        if ($allowed !== [] && ! collect($allowed)->contains(fn ($h) => $host === $h || str_ends_with($host, '.'.$h))) {
            return __('Audio must be hosted on one of these sites: :sites.', ['sites' => implode(', ', $allowed)]);
        }
        if ($this->isPrivateHost($host)) {
            return __('That link cannot be used.');
        }

        return null;
    }

    /** Localhost and private networks are never fetched, so a pasted link cannot be used to look inside our servers. */
    public function isPrivateHost(string $host): bool
    {
        if (in_array($host, ['localhost', '0.0.0.0'], true) || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return true;
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false; // does not resolve: let the link check treat it as unreachable
        }

        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** "24:10" or "1:02:05" or "24" (minutes) → seconds; null for empty or unreadable. */
    public static function parseDuration(?string $text): ?int
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        $parts = array_map('intval', explode(':', $text));
        if (count($parts) > 3 || preg_match('/[^0-9:]/', $text)) {
            return null;
        }

        return match (count($parts)) {
            1 => $parts[0] * 60,
            2 => $parts[0] * 60 + $parts[1],
            default => $parts[0] * 3600 + $parts[1] * 60 + $parts[2],
        };
    }

    // ── The super admin's review ────────────────────────────────────────────────────────────────────────

    public function approve(Podcast $podcast): void
    {
        $podcast->update(['status' => 'approved', 'review_note' => null, 'approved_at' => $podcast->approved_at ?? now()]);
        $this->tell($podcast, 'approved');
    }

    public function reject(Podcast $podcast, string $reason): void
    {
        $podcast->update(['status' => 'rejected', 'review_note' => $reason]);
        $this->tell($podcast, 'rejected', $reason);
    }

    public function hide(Podcast $podcast, ?string $reason = null): void
    {
        $podcast->update(['status' => 'hidden', 'review_note' => $reason]);
        $this->tell($podcast, 'hidden', $reason);
    }

    protected function tell(Podcast $podcast, string $outcome, ?string $reason = null): void
    {
        $podcast->owner?->notify(new PodcastReviewed($podcast, $outcome, $reason));
    }

    // ── Listeners ───────────────────────────────────────────────────────────────────────────────────────

    public function follow(Podcast $podcast, User $user): void
    {
        $podcast->followers()->syncWithoutDetaching([$user->id]);
        $podcast->refreshCounts();
    }

    public function unfollow(Podcast $podcast, User $user): void
    {
        $podcast->followers()->detach($user->id);
        $podcast->refreshCounts();
    }

    public function played(PodcastEpisode $episode): void
    {
        $episode->increment('plays_count');
    }
}
