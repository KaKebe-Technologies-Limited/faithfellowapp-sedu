<?php

namespace App\Services;

use App\Jobs\SendInspirationBatch;
use App\Models\DeviceToken;
use App\Models\Inspiration;
use App\Models\PlatformSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Sends the inspiring messages to people's phones as push notifications: by itself at set times of the day (the morning, mid-morning and evening
 * message, one of each every day), or right now when the super admin presses send. Only people whose phone has the app, whose "Daily inspiration"
 * notifications are on and who are not in their quiet hours get the push. The scheduled sends are one a day for each part of the day, even if the
 * server checks every minute, and a part of the day that was missed by more than a few hours is skipped rather than sent late.
 */
class InspirationBroadcaster
{
    public const DEFAULT_TIMES = ['morning' => '06:30', 'midmorning' => '10:30', 'evening' => '18:30'];

    /** How long after its time a scheduled message may still go out (a server that was down for a short while catches up). */
    private const LATE_MINUTES = 150;

    public function __construct(protected InspirationService $messages) {}

    /** Whether the scheduled sends are on (they are, until the super admin switches them off). */
    public function auto(): bool
    {
        return (string) PlatformSetting::get('inspiration_push', '1') !== '0';
    }

    /** @return array<string, string> slot => HH:MM */
    public function times(): array
    {
        return collect(self::DEFAULT_TIMES)->map(fn ($default, $slot) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) PlatformSetting::get("inspiration_time_{$slot}")) ? (string) PlatformSetting::get("inspiration_time_{$slot}") : $default)->all();
    }

    /** @param  array<string, string>  $times */
    public function save(bool $auto, array $times): void
    {
        PlatformSetting::put('inspiration_push', $auto ? '1' : '0');
        foreach (self::DEFAULT_TIMES as $slot => $default) {
            PlatformSetting::put("inspiration_time_{$slot}", $times[$slot] ?? $default);
        }
    }

    /** Phones that can get it: members who have the app on a phone. */
    public function audience(): int
    {
        return (int) DeviceToken::query()->distinct()->count('user_id');
    }

    /** Sends the parts of the day that are due. Returns how many messages went out. */
    public function sendDue(?Carbon $now = null): int
    {
        if (! $this->auto()) {
            return 0;
        }
        $now ??= now();
        $sent = 0;

        foreach ($this->times() as $slot => $time) {
            $at = $now->copy()->setTimeFromTimeString($time);
            if ($now->lt($at) || $now->gt($at->copy()->addMinutes(self::LATE_MINUTES))) {
                continue;
            }
            // One a day for each part of the day, whichever server minute gets here first.
            if (! Cache::add("inspiration-pushed:{$now->toDateString()}:{$slot}", 1, now()->addDay())) {
                continue;
            }
            $message = $this->messages->forSlot($slot, $now);
            if ($message) {
                $this->broadcast($slot, $message['body'], $message['reference']);
                PlatformSetting::put("inspiration_last_{$slot}", $now->toIso8601String());
                $sent++;
            }
        }

        return $sent;
    }

    /** Sends one message now to everyone who can get it. Returns how many people it was queued for. */
    public function sendNow(Inspiration $message): int
    {
        $count = $this->broadcast($message->slot, $message->body, $message->reference);
        PlatformSetting::put('inspiration_last_instant', now()->toIso8601String());

        return $count;
    }

    protected function broadcast(string $slot, string $body, ?string $reference): int
    {
        $text = $body.($reference ? " ({$reference})" : '');
        $payload = [
            'title' => __(match ($slot) {
                'morning' => '☀️ Morning inspiration', 'midmorning' => '🔥 Midday strength', default => '🌙 Evening peace'
            }),
            'body' => mb_strlen($text) > 170 ? mb_substr($text, 0, 167).'…' : $text,
            'data' => ['kind' => 'inspiration'],
            'channelId' => 'default',
        ];

        $total = 0;
        DeviceToken::query()->distinct()->orderBy('user_id')->pluck('user_id')->chunk(200)->each(function ($ids) use ($payload, &$total) {
            SendInspirationBatch::dispatch($ids->values()->all(), $payload);
            $total += $ids->count();
        });

        return $total;
    }
}
