<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Services\Bible\BibleApiComProvider;
use App\Services\Bible\BibleService;
use App\Support\VerseOfTheDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The verse of the day, pushed to every phone that has the app each morning: a notification with the reference and the words. It goes out by
 * itself at the time set (06:00 by default), once a day even if the server checks every minute, and a morning missed by more than a few hours is
 * skipped rather than sent late. The words are from the World English Bible (public domain), the same for everyone.
 */
class VerseBroadcaster
{
    public const DEFAULT_TIME = '06:00';

    /** How long after its time the verse may still go out (a server that was down for a short while catches up). */
    private const LATE_MINUTES = 240;

    public function __construct(protected PushBroadcaster $push, protected BibleService $bible) {}

    /** Whether the daily send is on (it is, until the super admin switches it off). */
    public function auto(): bool
    {
        return (string) PlatformSetting::get('verse_push', '1') !== '0';
    }

    public function time(): string
    {
        $time = (string) PlatformSetting::get('verse_push_time');

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) ? $time : self::DEFAULT_TIME;
    }

    public function save(bool $auto, string $time): void
    {
        PlatformSetting::put('verse_push', $auto ? '1' : '0');
        PlatformSetting::put('verse_push_time', $time);
    }

    /** Sends today's verse when it is time. Returns whether it went out. */
    public function sendDue(?Carbon $now = null): bool
    {
        if (! $this->auto()) {
            return false;
        }
        $now ??= now();
        $at = $now->copy()->setTimeFromTimeString($this->time());
        if ($now->lt($at) || $now->gt($at->copy()->addMinutes(self::LATE_MINUTES))) {
            return false;
        }
        // One a day, whichever server minute gets here first.
        if (! Cache::add("verse-pushed:{$now->toDateString()}", 1, now()->addDay())) {
            return false;
        }
        $this->sendNow($now);

        return true;
    }

    /** Sends the verse of that day (today) to everyone now. Returns how many people it was sent to. */
    public function sendNow(?Carbon $day = null): int
    {
        $count = $this->push->toEveryone('verse', $this->message($day ?? now()));
        PlatformSetting::put('verse_push_last', now()->toIso8601String());

        return $count;
    }

    /**
     * What the notification says: the reference and the words, or the reference alone when the Bible service cannot be reached right now.
     *
     * @return array<string, mixed>
     */
    public function message(Carbon $day): array
    {
        $reference = VerseOfTheDay::reference($day);
        $passage = null;
        BibleApiComProvider::$timeout = 5;
        try {
            $passage = rescue(fn () => $this->bible->passage($reference, 'web'), null, false);
        } finally {
            BibleApiComProvider::$timeout = null;
        }
        $section = $passage['sections'][0] ?? null;
        $words = $section && ! ($section['error'] ?? null) ? trim(preg_replace('/\s+/', ' ', collect($section['verses'])->pluck('t')->implode(' ')) ?? '') : '';
        $title = $passage['title'] ?? $reference;

        return [
            'title' => __('📖 Verse of the day'),
            'body' => $words !== '' ? Str::limit("“{$words}” — {$title}", 170) : __(':reference — tap to read today\'s verse.', ['reference' => $title]),
            'data' => array_filter([
                'kind' => 'verse', 'url' => '/bible', 'reference' => $title,
                'book' => $section['book'] ?? null, 'chapter' => isset($section['chapter']) ? (int) $section['chapter'] : null,
            ], fn ($v) => $v !== null),
            'channelId' => 'default',
        ];
    }
}
