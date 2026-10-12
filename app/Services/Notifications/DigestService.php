<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\Member\DailyDigest;
use Illuminate\Support\Facades\DB;

/**
 * Daily digest for members who prefer one summary over many alerts.
 * The source is the in-app notifications table, so nothing extra is stored.
 */
class DigestService
{
    public function sendDue(): int
    {
        $sent = 0;

        User::where('digest_mode', true)->whereNotNull('email_verified_at')->where('status', 'active')
            ->chunkById(200, function ($users) use (&$sent) {
                foreach ($users as $user) {
                    $since = max($user->last_digest_at ?? now()->subDay(), now()->subDays(2));

                    $unread = DB::table('notifications')
                        ->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)
                        ->whereNull('read_at')->where('created_at', '>', $since);

                    $total = (clone $unread)->count();
                    if ($total === 0) {
                        continue;
                    }

                    $items = $unread->latest()->take(20)->pluck('data')
                        ->map(fn ($json) => json_decode($json, true) ?: [])
                        ->map(fn ($d) => ['title' => $d['title'] ?? __('Update'), 'church' => $d['church'] ?? null, 'url' => $d['url'] ?? null]);

                    $user->notify(new DailyDigest($items, $total));
                    $user->forceFill(['last_digest_at' => now()])->saveQuietly();
                    $sent++;
                }
            });

        return $sent;
    }
}
