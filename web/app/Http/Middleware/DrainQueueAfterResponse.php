<?php

namespace App\Http\Middleware;

use App\Support\QueueHealth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emails, notifications and pushes (an announcement reaching every phone) wait in the queue, and a shared server often has no cron job to empty it.
 * So after a visit has been answered, at most once a minute and only when something is waiting, the visit's own process works the queue for a few
 * seconds. Nobody waits for it, a real worker or cron job simply finds the queue empty, and notifications no longer depend on the hosting being set up.
 */
class DrainQueueAfterResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! QueueHealth::usesDatabase() || ! Cache::add('queue.drain.lock', 1, 50) || ! QueueHealth::hasWaiting()) {
            return;
        }

        rescue(function () {
            @set_time_limit(45);
            Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 20, '--max-jobs' => 40, '--tries' => 2, '--quiet' => true]);
            Cache::put('queue.drained_at', time(), 86400);
        }, null, true);
    }
}
