<?php

use App\Http\Middleware\DrainQueueAfterResponse;
use App\Support\QueueHealth;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/*
| Notifications (an announcement reaching every phone), emails and pushes wait in the queue. On shared hosting nothing may be emptying it, so after a visit
| has been answered, at most once a minute and only when something is waiting, the visit's own process works the queue. Super admin → Push test says whether
| the hosting's cron job runs and how much is waiting.
*/

class DrainProbeJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public string $key) {}

    public function handle(): void
    {
        Cache::put($this->key, true);
    }
}

function theDatabaseQueue(): void
{
    config(['queue.default' => 'database']);
    DB::table('jobs')->delete();
    Cache::forget('queue.drain.lock');
    Cache::forget('queue.drained_at');
}

function afterAVisit(): void
{
    (new DrainQueueAfterResponse)->terminate(Request::create('/api/v1/home'), new Response);
}

test('a visit empties what is waiting in the queue, once a minute', function () {
    theDatabaseQueue();
    DrainProbeJob::dispatch('first');
    expect(QueueHealth::hasWaiting())->toBeTrue()->and(Cache::has('first'))->toBeFalse();

    afterAVisit();
    expect(Cache::has('first'))->toBeTrue()->and(QueueHealth::hasWaiting())->toBeFalse()->and(Cache::get('queue.drained_at'))->not->toBeNull();

    // Another visit in the same minute leaves the next job for the next minute.
    DrainProbeJob::dispatch('second');
    afterAVisit();
    expect(Cache::has('second'))->toBeFalse();
    Cache::forget('queue.drain.lock');
    afterAVisit();
    expect(Cache::has('second'))->toBeTrue();
});

test('nothing happens when the queue is empty or runs at once', function () {
    theDatabaseQueue();
    afterAVisit();
    expect(Cache::get('queue.drained_at'))->toBeNull();

    config(['queue.default' => 'sync']);
    Cache::forget('queue.drain.lock');
    afterAVisit();
    expect(Cache::get('queue.drained_at'))->toBeNull();
});

test('Super admin shows whether the cron job runs and what is waiting', function () {
    theDatabaseQueue();
    $admin = superAdmin();

    $this->actingAs($admin)->get(route('super-admin.push.index'))->assertOk()->assertSee('Are notifications leaving the server?')->assertSee('Not seen yet')->assertSee('Not needed yet');

    Cache::put('scheduler.ran_at', time() - 30);
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time() - 900, 'created_at' => time() - 900]);
    $this->actingAs($admin)->get(route('super-admin.push.index'))->assertOk()->assertSee('Running')->assertSee('nothing is emptying the queue');
    expect(QueueHealth::summary())->toMatchArray(['waiting' => 1])->and(QueueHealth::summary()['oldest_seconds'])->toBeGreaterThanOrEqual(900);
});
