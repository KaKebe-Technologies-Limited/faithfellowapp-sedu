<?php

namespace App\Services;

use App\Models\Church;
use App\Models\JoinLinkVisit;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * The link every church shares to bring people into the app: https://faithfellows.com/join/FF47KM (the code is FF, two digits and two letters).
 *
 * Opening it shows the church and either opens the app on that church or sends the person to download the app. The app keeps the code
 * until the person has an account, then follows the church for them. When the app is installed after the link was opened, three things
 * can still tell it which church: the code in the Play Store install referrer (Android), the person typing the code, and a match on the
 * address the link was opened from (only offered, never applied without a yes, because many phones share one address).
 */
class JoinLinks
{
    /** The letters of a code: no I and no O, which look like 1 and 0 when read aloud or typed. */
    public const LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    /** The shortest thing that can be a code (a typed code shorter than this is not looked up). */
    public const MIN_LENGTH = 4;

    /** How long after opening the link the app may still be offered that church, and how long the fingerprint is kept. */
    public const MATCH_HOURS = 24;

    public const KEEP_DAYS = 3;

    /** True once the database update that adds the codes has been applied (the live site applies it from Super admin → Database updates). */
    public static function ready(): bool
    {
        static $ready = null;

        return $ready ??= (bool) rescue(fn () => Schema::hasColumn('churches', 'join_code') && Schema::hasTable('join_link_visits'), false, false);
    }

    /** "https://faithfellows.com/join/ff47km?x=1", "faithfellows://join/FF47KM" or "ff 47 km" all become FF47KM. */
    public static function normalise(?string $input): string
    {
        $text = trim((string) $input);
        if (preg_match('~/join/([A-Za-z0-9\-]+)~', $text, $m)) {
            $text = $m[1];
        }

        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $text));
    }

    /**
     * A new code: FF, two digits and two letters, six characters like FF47KM, easy to say and to type. About 57,000 are possible; a code that is
     * already taken is never made again (and if they ever run out, a digit is added).
     */
    public static function generate(): string
    {
        $make = fn (int $digits) => 'FF'.str_pad((string) random_int(0, 10 ** $digits - 1), $digits, '0', STR_PAD_LEFT)
            .self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)].self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)];

        foreach ([2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 3, 3, 3, 3, 3, 4] as $digits) {
            $code = $make($digits);
            if (! Church::withTrashed()->where('join_code', $code)->exists()) {
                return $code;
            }
        }

        return $make(6);
    }

    /** The church's code, made on the spot for a church that has none yet. Null before the database update. */
    public function codeFor(Church $church): ?string
    {
        if (! self::ready()) {
            return null;
        }
        if (blank($church->join_code)) {
            $church->forceFill(['join_code' => self::generate()])->saveQuietly();
        }

        return $church->join_code;
    }

    /** The link to share. */
    public function url(Church $church): ?string
    {
        $code = $this->codeFor($church);

        return $code ? route('join.show', ['code' => $code]) : null;
    }

    /** The church behind a code or a pasted link. Only churches that can take new followers are found. */
    public function find(?string $input): ?Church
    {
        $code = self::normalise($input);
        if (! self::ready() || strlen($code) < self::MIN_LENGTH) {
            return null;
        }

        return Church::active()->where('join_code', $code)->first();
    }

    /** A new random code, for a church whose link should stop working (the old link then shows nothing). */
    public function regenerate(Church $church): string
    {
        $code = self::generate();
        $church->forceFill(['join_code' => $code])->save();

        return $code;
    }

    /** Notes that the link was opened. Never allowed to break the page. */
    public function record(Church $church, Request $request): void
    {
        rescue(fn () => JoinLinkVisit::create([
            'church_id' => $church->id,
            'platform' => self::platformOf((string) $request->userAgent()),
            'fingerprint' => $this->fingerprint($request->ip(), self::platformOf((string) $request->userAgent())),
        ]), null, false);
    }

    public static function platformOf(string $userAgent): string
    {
        return match (true) {
            stripos($userAgent, 'Android') !== false => 'android',
            (bool) preg_match('/iPhone|iPad|iPod/i', $userAgent) => 'ios',
            default => 'other',
        };
    }

    /**
     * The church whose link this phone opened lately, for the app opened for the first time. Only when it is unambiguous (one church opened from this
     * address), and each opening is offered once. The app asks the person before it uses the answer.
     */
    public function match(Request $request, string $platform): ?Church
    {
        if (! self::ready() || ! in_array($platform, ['android', 'ios'], true)) {
            return null;
        }

        $visits = JoinLinkVisit::where('fingerprint', $this->fingerprint($request->ip(), $platform))->whereNull('claimed_at')
            ->where('created_at', '>=', now()->subHours(self::MATCH_HOURS))->get();
        if ($visits->isEmpty() || $visits->pluck('church_id')->unique()->count() > 1) {
            return null;
        }

        JoinLinkVisit::whereIn('id', $visits->pluck('id'))->update(['claimed_at' => now()]);

        return Church::active()->find($visits->first()->church_id);
    }

    /** What the church sees on its invite page. */
    public function stats(Church $church): array
    {
        if (! self::ready()) {
            return ['opened' => 0, 'opened_30' => 0, 'joined' => 0, 'joined_30' => 0];
        }
        $visits = JoinLinkVisit::where('church_id', $church->id);
        $joined = Subscription::where('church_id', $church->id)->where('source', 'invite')->whereIn('status', ['active', 'pending']);

        return [
            'opened' => (clone $visits)->count(),
            'opened_30' => (clone $visits)->where('created_at', '>=', now()->subDays(30))->count(),
            'joined' => (clone $joined)->count(),
            'joined_30' => (clone $joined)->where('subscribed_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /** Forgets whose address opened which link once it is too old to be of use. The counts stay. */
    public function prune(): int
    {
        if (! self::ready()) {
            return 0;
        }

        return JoinLinkVisit::whereNotNull('fingerprint')->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->update(['fingerprint' => null]);
    }

    /** A hash of the address (a phone's /64 for IPv6) and the phone kind, mixed with the app key: it cannot be turned back into an address. */
    protected function fingerprint(?string $ip, string $platform): ?string
    {
        if (! $ip) {
            return null;
        }
        $packed = @inet_pton($ip);
        $address = $packed !== false && strlen($packed) === 16 ? bin2hex(substr($packed, 0, 8)) : $ip;

        return hash_hmac('sha256', $address.'|'.$platform, (string) config('app.key'));
    }
}
