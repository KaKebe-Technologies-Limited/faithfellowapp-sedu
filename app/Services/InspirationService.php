<?php

namespace App\Services;

use App\Models\Inspiration;
use Illuminate\Support\Carbon;

/**
 * Which inspiring message the home screen shows now. The day has three parts (morning 5:00 to 9:59, mid-morning 10:00 to 15:59,
 * evening from 16:00); each part shows one message from its own library, and it changes every day, the same for everyone.
 */
class InspirationService
{
    public function slotAt(Carbon $time): string
    {
        $hour = (int) $time->format('G');

        return match (true) {
            $hour >= 5 && $hour < 10 => 'morning',
            $hour >= 10 && $hour < 16 => 'midmorning',
            default => 'evening',
        };
    }

    /** @return array{slot: string, label: string, body: string, reference: ?string}|null */
    public function current(?Carbon $time = null): ?array
    {
        $time ??= now();
        $slot = $this->slotAt($time);
        $messages = Inspiration::where('slot', $slot)->where('is_active', true)->orderBy('id')->get();
        if ($messages->isEmpty()) {
            return null;
        }

        // The small hours (before 5:00) still belong to yesterday's evening.
        $day = (int) $time->copy()->subHours(5)->format('z');
        $message = $messages[$day % $messages->count()];

        return ['slot' => $slot, 'label' => __(Inspiration::SLOTS[$slot]), 'body' => $message->body, 'reference' => $message->reference];
    }
}
