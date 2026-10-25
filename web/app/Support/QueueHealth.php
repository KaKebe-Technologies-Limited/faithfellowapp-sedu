<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Are emails, notifications and pushes leaving the server? They wait in the queue, and something has to empty it each minute: the hosting's cron job
 * (which runs the scheduler) or, when there is none, the first visit of each minute (see DrainQueueAfterResponse). This reads what both leave behind.
 */
class QueueHealth
{
    /** True when the queue is the database table (the setting on shared hosting). */
    public static function usesDatabase(): bool
    {
        return config('queue.connections.'.config('queue.default').'.driver') === 'database';
    }

    protected static function table(): string
    {
        return (string) config('queue.connections.database.table', 'jobs');
    }

    /** Whether anything is ready to be sent right now. */
    public static function hasWaiting(): bool
    {
        return (bool) rescue(fn () => DB::table(self::table())->whereNull('reserved_at')->where('available_at', '<=', time())->exists(), false, false);
    }

    /** @return array{waiting: int, oldest_seconds: int|null, failed: int, scheduler_ran_at: int|null, drained_at: int|null} */
    public static function summary(): array
    {
        $waiting = (array) rescue(fn () => DB::table(self::table())->whereNull('reserved_at')->where('available_at', '<=', time())->selectRaw('COUNT(*) as n, MIN(available_at) as oldest')->first(), [], false);
        $oldest = isset($waiting['oldest']) ? (int) $waiting['oldest'] : null;

        return [
            'waiting' => (int) ($waiting['n'] ?? 0),
            'oldest_seconds' => $oldest ? max(0, time() - $oldest) : null,
            'failed' => (int) rescue(fn () => DB::table('failed_jobs')->count(), 0, false),
            'scheduler_ran_at' => Cache::get('scheduler.ran_at'),
            'drained_at' => Cache::get('queue.drained_at'),
        ];
    }
}
